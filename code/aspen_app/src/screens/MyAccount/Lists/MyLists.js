import React, {Component} from "react";
import AsyncStorage from '@react-native-async-storage/async-storage';
import {Badge, Box, Center, FlatList, Image, Pressable, Text, HStack, VStack, ScrollView} from "native-base";
import moment from "moment";

// custom components and helper files
import {loadingSpinner} from "../../../components/loadingSpinner";
import CreateList from "./CreateList";
import {getHolds, getLists} from "../../../util/loadPatron";
import {userContext} from "../../../context/user";

export default class MyLists extends Component {
	constructor() {
		super();
		this.state = {
			isLoading: true,
			hasError: false,
			error: null,
			libraryUrl: '',
			lists: []
		};
		this._fetchLists();
	}

	_fetchLists = async () => {
		const { route } = this.props;
		const libraryUrl = route.params?.libraryUrl ?? 'null';

		await getLists(libraryUrl).then(response =>
			this.setState({
				lists: response
			})
		);
	}

	componentDidMount = async () => {
		await this._fetchLists().then(r => {
			this.setState({
				isLoading: false
			})
		});

		this.interval = setInterval(() => {
			this._fetchLists();
		}, 1000)
		return () => clearInterval(this.interval)

	};

	componentWillUnmount() {
		clearInterval(this.interval);
	}

	// renders the items on the screen
	renderList = (item, libraryUrl) => {
		let lastUpdated = moment.unix(item.dateUpdated);
		lastUpdated = moment(lastUpdated).format("MMM D, YYYY");
		let privacy = "Private";
		if(item.public === 1) {
			privacy = "Public";
		}
		if(item.id !== "recommendations") {
			return (
				<Pressable onPress={() => {this.openList(item.id, item, libraryUrl)}} borderBottomWidth="1" _dark={{ borderColor: "gray.600" }} borderColor="coolGray.200" pl="1" pr="1" py="2">
					<HStack space={3} justifyContent="flex-start">
						<VStack space={1}>
							<Image source={{uri: item.cover}} alt={item.title} size="lg" resizeMode="contain" />
							<Badge mt={1}>{privacy}</Badge>
						</VStack>
						<VStack space={1} justifyContent="space-between" maxW="80%">
							<Box>
								<Text bold fontSize="md">{item.title}</Text>
								{item.description ? (<Text fontSize="xs" mb={2}>{item.description}</Text>) : null}
								<Text fontSize="9px" italic>Last updated on {lastUpdated}</Text>
								<Text fontSize="9px" italic>{item.numTitles} items</Text>
							</Box>
						</VStack>
					</HStack>
				</Pressable>
			);
		}
	};

	openList = (id, item, libraryUrl) => {
		this.props.navigation.navigate("AccountScreenTab", {screen: 'List', params: { list: id, details: item, name: item.title, libraryUrl: libraryUrl }});
	};

	_listEmptyComponent = () => {
		return (
			<Center mt={5} mb={5}>
				<Text bold fontSize="lg">
					You have no lists
				</Text>
			</Center>
		);
	};


	static contextType = userContext;

	render() {
		const {lists} = this.state;
		const user = this.context.user;
		const location = this.context.location;
		const library = this.context.library;

		if (this.state.isLoading) {
			return (loadingSpinner());
		}

		return (
			<ScrollView>
			<Box safeArea={2} h="100%">
				<CreateList libraryUrl={library.baseUrl} />
				<FlatList
					data={lists}
					ListEmptyComponent={this._listEmptyComponent()}
					renderItem={({item}) => this.renderList(item, library.baseUrl)}
					keyExtractor={(item) => item.id}
				/>
			</Box>
			</ScrollView>
		);

	}
}