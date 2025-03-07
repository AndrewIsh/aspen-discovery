import React, {Component} from "react";
import AsyncStorage from '@react-native-async-storage/async-storage';
import {Box, FlatList, HStack, Switch, Text, ScrollView} from "native-base";

// custom components and helper files
import {loadError} from "../../../components/loadError";
import {getPatronBrowseCategories} from "../../../util/loadPatron";
import {dismissBrowseCategory, showBrowseCategory} from "../../../util/accountActions";
import {userContext} from "../../../context/user";
import {loadingSpinner} from "../../../components/loadingSpinner";
import {getBrowseCategories} from "../../../util/loadLibrary";

export default class Settings_HomeScreen extends Component {
	constructor(props, context) {
		super(props, context);
		this.state = {
			isLoading: true,
			hasError: false,
			error: null,
			hasUpdated: false,
			isRefreshing: false,
			browseCategories: [],
			patronCategories: [],
		};
		//getPatronBrowseCategories();
		this.loadBrowseCategories();
	}

	// store the values into the state
	componentDidMount = async () => {
		let discoveryVersion = "22.04.00";
		if(this.context.library.discoveryVersion) {
			let version = this.context.library.discoveryVersion;
			version = version.split(" ");
			discoveryVersion = version[0];
		}

		this.setState({
			isLoading: true,
			discoveryVersion: discoveryVersion,
		})

		await this.loadBrowseCategories().then(r => {
			this.setState({ isLoading: false });
		});

		this.interval = setInterval(() => {
			this.loadBrowseCategories();
		}, 1000)

		return () => clearInterval(this.interval)

	};

	componentWillUnmount() {
		clearInterval(this.interval);
	}

	loadBrowseCategories = async () => {
		const { route } = this.props;
		const libraryUrl = route.params?.libraryUrl ?? 'null';
		const patronId = route.params?.patronId ?? 'null';

		await getPatronBrowseCategories(libraryUrl, patronId).then(res => {
			this.setState({
				patronCategories: res,
				isLoading: false,
			})
		})
	}

	// Update the status of the browse category when the toggle switch is flipped
	updateToggle = async (item, user, libraryUrl) => {
		if (item.isHidden === true) {
			await showBrowseCategory(libraryUrl, item.key, user, this.state.discoveryVersion).then(async res => {
				await getPatronBrowseCategories(libraryUrl, patronId).then(res => {
					this.setState({
						patronCategories: res,
					})
				})
				await getBrowseCategories(libraryUrl, this.state.discoveryVersion).then(response => {
					this.context.browseCategories = response;
				})
			});
		} else {
			await dismissBrowseCategory(libraryUrl, item.key, user, this.state.discoveryVersion).then(async res => {
				await getPatronBrowseCategories(libraryUrl, patronId).then(res => {
					this.setState({
						patronCategories: res,
					})
				})
				await getBrowseCategories(libraryUrl, this.state.discoveryVersion).then(response => {
					this.context.browseCategories = response;
				})
			});
		}
	};


	renderNativeItem = (item, patronId, libraryUrl, browseCategories) => {
		return (
			<Box borderBottomWidth="1" _dark={{ borderColor: "gray.600" }} borderColor="coolGray.200" pl="4" pr="5" py="2">
				<HStack space={3} alignItems="center" justifyContent="space-between" pb={1}>
					<Text isTruncated bold maxW="80%" fontSize={{base: "lg", lg: "xl"}}>{item.title}</Text>
					{item.isHidden ? <Switch size={{base: "md", lg: "lg"}} onValueChange={() => this.updateToggle(item, patronId, libraryUrl, browseCategories)}/> :
						<Switch size={{base: "md", lg: "lg"}} onValueChange={() => this.updateToggle(item, patronId, libraryUrl, browseCategories)} defaultIsChecked/>}
				</HStack>
			</Box>
		);
	};

	static contextType = userContext;

	render() {
		const {patronCategories} = this.state;
		const user = this.context.user;
		const location = this.context.location;
		const library = this.context.library;
		const browseCategories = this.context.browseCategories;

		if (this.state.isLoading === true) {
			return (loadingSpinner());
		}

		if (this.state.hasError) {
			return (loadError(this.state.error));
		}

		if (!patronCategories) {
			return (loadError("Unable to load browse categories"));
		}

		return (
			<ScrollView>
			<Box>
				<FlatList
					data={patronCategories}
					renderItem={({item}) => this.renderNativeItem(item, user.id, library.baseUrl, browseCategories)}
				/>
			</Box>
			</ScrollView>
		);
	}
}
