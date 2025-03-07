import React, {Component} from "react";
import AsyncStorage from '@react-native-async-storage/async-storage';
import {Box, Button, Center, FlatList, FormControl, Input, Text} from "native-base";
import _ from "lodash";

// custom components and helper files
import {translate} from '../../translations/translations';
import {loadingSpinner} from "../../components/loadingSpinner";
import {userContext} from "../../context/user";

export default class Search extends Component {
	constructor() {
		super();
		this.state = {
			isLoading: true,
			searchTerm: "",
		};
	}

	componentDidMount = async () => {
		this.setState({
			isLoading: false,
		});

	};

	initiateSearch = async () => {
		const {searchTerm, libraryUrl} = this.state;
		const { navigation } = this.props;
		navigation.navigate("SearchResults", {
			searchTerm: searchTerm,
			libraryUrl: libraryUrl,
		});
	};

	renderItem = (item, libraryUrl) => {
		const { navigation } = this.props;
		return (
			<Button
				mb={3}
				onPress={() =>
					navigation.navigate("SearchResults", {
						searchTerm: item.searchTerm,
						libraryUrl: libraryUrl,
					})
				}
			>
				{item.label}
			</Button>
		);
	};

	clearText = () => {
		this.setState({searchTerm: ""});
	};

	static contextType = userContext;

	render() {
		const user = this.context.user;
		const location = this.context.location;
		const library = this.context.library;

		const quickSearchNum = _.size(library.quickSearches);

		if (this.state.isLoading) {
			return (loadingSpinner());
		}

		return (
			<Box safeArea={5}>
				<FormControl>
					<Input
						variant="filled"
						autoCapitalize="none"
						onChangeText={(searchTerm) => this.setState({searchTerm, libraryUrl: library.baseUrl})}
						status="info"
						placeholder={translate('search.title')}
						clearButtonMode="always"
						onSubmitEditing={this.initiateSearch}
						value={this.state.searchTerm}
						size="xl"
					/>
				</FormControl>

				{quickSearchNum > 0 ?
				<Center>
					<Text mt={8} mb={2} fontSize="xl" bold>
						{translate('search.quick_search_title')}
					</Text>
				</Center>
				: null }
					<FlatList
						data={_.sortBy(library.quickSearches, ['weight', 'label'])}
						renderItem={({item}) => this.renderItem(item, library.baseUrl)}
					/>
			</Box>
		);
	}
}