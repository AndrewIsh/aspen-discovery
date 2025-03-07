<?php

require_once ROOT_DIR . '/Action.php';

class AJAX extends Action {

	function launch()
	{
		$method = (isset($_GET['method']) && !is_array($_GET['method'])) ? $_GET['method'] : '';
		if (method_exists($this, $method)) {
			header('Content-type: application/json');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

			$result = $this->$method();
			echo json_encode($result);
		}else {
			$output = json_encode(array('error'=>'invalid_method'));
			echo $output;
		}
	}

	// Email Search Results
	/** @noinspection PhpUnused */
	function sendEmail()
	{
		global $interface;

		$subject = translate(['text' => 'Library Catalog Search Result', 'isPublicFacing'=>true]);
		$url = $_REQUEST['sourceUrl'];
		$to = $_REQUEST['to'];
		$from = isset($_REQUEST['from']) ? $_REQUEST['from'] : '';
		$message = $_REQUEST['message'];
		if (strpos($message, 'http') === false && strpos($message, 'mailto') === false && $message == strip_tags($message)){
			$interface->assign('message', $message);
			$interface->assign('msgUrl', $url);
			$interface->assign('from', $from);
			$body = $interface->fetch('Emails/share-link.tpl');

			require_once ROOT_DIR . '/sys/Email/Mailer.php';
			$mail = new Mailer();
			$emailResult = $mail->send($to, $subject, $body);

			if ($emailResult === true){
				$result = array(
						'result' => true,
						'message' => 'Your email was sent successfully.'
				);
			}elseif (($emailResult instanceof AspenError)){
				$result = array(
						'result' => false,
						'message' => "Your email message could not be sent: {$emailResult->getMessage()}."
				);
			}else{
				$result = array(
						'result' => false,
						'message' => 'Your email message could not be sent due to an unknown error.'
				);
			}
		}else{
			$result = array(
					'result' => false,
					'message' => 'Sorry, we can&apos;t send emails with html or other data in it.'
			);
		}

		return $result;
	}

	/** @noinspection PhpUnused */
	function getAutoSuggestList()
	{
		require_once ROOT_DIR . '/sys/SearchSuggestions.php';
		global $timer;
		global $configArray;
		global $memCache;
		$searchTerm = isset($_REQUEST['searchTerm']) ? $_REQUEST['searchTerm'] : $_REQUEST['q'];
		$searchIndex = isset($_REQUEST['searchIndex']) ? $_REQUEST['searchIndex'] : '';
		$searchSource = !empty($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : '';
		$cacheKey = 'auto_suggest_list_' . urlencode($searchSource) . '_' . urlencode($searchIndex) . '_' . urlencode($searchTerm);
		$searchSuggestions = $memCache->get($cacheKey);
		if ($searchSuggestions == false || isset($_REQUEST['reload'])){
			$suggestions = new SearchSuggestions();
			$commonSearches = $suggestions->getAllSuggestions($searchTerm, $searchIndex, $searchSource);
			$commonSearchTerms = array();
			foreach ($commonSearches as $searchTerm){
				if (is_array($searchTerm)){
					$plainText = preg_replace('~</?b>~i', '', $searchTerm['phrase']);
					$plainText = str_replace(':', '', $plainText);
					$plainText = preg_replace('~\s{2,}~', ' ', $plainText);
					$commonSearchTerms[] = [
						'label' => $searchTerm['phrase'],
						'value' => $plainText
					];
				}else{
					$commonSearchTerms[] = $searchTerm;
				}
			}
			$searchSuggestions = $commonSearchTerms;
			$memCache->set($cacheKey, $searchSuggestions, $configArray['Caching']['search_suggestions'] );
			$timer->logTime("Loaded search suggestions $cacheKey");
		}
		return $searchSuggestions;
	}

	/** @noinspection PhpUnused */
	function getProspectorResults(){
		$prospectorSavedSearchId = $_GET['prospectorSavedSearchId'];

		require_once ROOT_DIR . '/Drivers/marmot_inc/Prospector.php';
		global $interface;
		global $library;
		global $timer;

		/** @var SearchObject_AbstractGroupedWorkSearcher $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();
		$searchObject = $searchObject->restoreSavedSearch($prospectorSavedSearchId, false);

		//Load results from Prospector
		$prospector = new Prospector();

		// Only show prospector results within search results if enabled
		if ($library && $library->enableProspectorIntegration && $library->showProspectorResultsAtEndOfSearch){
			$prospectorResults = $prospector->getTopSearchResults($searchObject->getSearchTerms(), 5);
			$interface->assign('prospectorResults', $prospectorResults['records']);
		}

		$prospectorLink = $prospector->getSearchLink($searchObject->getSearchTerms());
		$interface->assign('prospectorLink', $prospectorLink);
		$timer->logTime('load Prospector titles');
		//echo $interface->fetch('Search/ajax-innreach.tpl');
		return array(
			'numTitles' => count($prospectorResults),
			'formattedData' => $interface->fetch('Search/ajax-innreach.tpl')
		);
	}

	/**
	 * @return array data representing the list information
	 */
	/** @noinspection PhpUnused */
	function getListTitles(){
		global $timer;

		$listName = strip_tags(isset($_GET['scrollerName']) ? $_GET['scrollerName'] : 'List' . $_GET['id']);

		//Determine the caching parameters
		require_once(ROOT_DIR . '/services/API/ListAPI.php');
		$listAPI = new ListAPI();

		global $interface;
		$interface->assign('listName', $listName);

		$showRatings = isset($_REQUEST['showRatings']) && $_REQUEST['showRatings'];
		$interface->assign('showRatings', $showRatings); // overwrite values that come from library settings

		$numTitlesToShow = isset($_REQUEST['numTitlesToShow']) ? $_REQUEST['numTitlesToShow'] : 25;

		$titles = $listAPI->getListTitles(null, $numTitlesToShow);
		$timer->logTime("getListTitles");
		if ($titles['success'] == true){
			$titles = $titles['titles'];
			if (is_array($titles)){
				foreach ($titles as $key => $rawData){
					$interface->assign('key', $key);
					// 20131206 James Staub: bookTitle is in the list API and it removes the final front slash, but I didn't get $rawData['bookTitle'] to load

					$titleShort = preg_replace(array('/:.*?$/', '/\s*\/$\s*/'),'', $rawData['title']);

					$imageUrl = $rawData['small_image'];
					if (isset($_REQUEST['coverSize']) && $_REQUEST['coverSize'] == 'medium'){
						$imageUrl = $rawData['image'];
					}

					$interface->assign('title',       $titleShort);
					$interface->assign('author',      $rawData['author']);
					$interface->assign('description', isset($rawData['description']) ? $rawData['description'] : null);
					$interface->assign('length',      isset($rawData['length']) ? $rawData['length'] : null);
					$interface->assign('publisher',   isset($rawData['publisher']) ? $rawData['publisher'] : null);
					$interface->assign('shortId',     $rawData['shortId']);
					$interface->assign('id',          $rawData['id']);
					$interface->assign('titleURL',    $rawData['titleURL']);
					$interface->assign('imageUrl',    $imageUrl);

					if ($showRatings){
						$interface->assign('ratingData', $rawData['ratingData']);
						$interface->assign('showNotInterested', false);
					}

					$rawData['formattedTitle']         = $interface->fetch('CollectionSpotlight/formattedTitle.tpl');
					$rawData['formattedTextOnlyTitle'] = $interface->fetch('CollectionSpotlight/formattedTextOnlyTitle.tpl');
					// TODO: Modify these for Archive Objects

					$titles[$key] = $rawData;
				}
			}
			$currentIndex = count($titles) > 5 ? floor(count($titles) / 2) : 0;

			$listData = array('titles' => $titles, 'currentIndex' => $currentIndex);

		}else{
			$listData = array('titles' => array(), 'currentIndex' => 0);
			if ($titles['message']) $listData['error'] = $titles['message']; // send error message to javascript
		}

		return $listData;
	}

	/** @noinspection PhpUnused */
	function getSpotlightTitles(){
		global $interface;
		$listName = strip_tags(isset($_GET['scrollerName']) ? $_GET['scrollerName'] : 'List' . $_GET['id']);
		$interface->assign('listName', $listName);

		require_once ROOT_DIR . '/sys/LocalEnrichment/CollectionSpotlightList.php';
		$collectionSpotlightList = new CollectionSpotlightList();
		$collectionSpotlightList->id = $_REQUEST['id'];
		if ($collectionSpotlightList->find(true)){
			$result = [
				'success' => true,
				'titles' => []
			];
			require_once ROOT_DIR . '/sys/LocalEnrichment/CollectionSpotlight.php';
			$collectionSpotlight = new CollectionSpotlight();
			$collectionSpotlight->id = $collectionSpotlightList->collectionSpotlightId;
			$collectionSpotlight->find(true);

			$interface->assign('collectionSpotlight', $collectionSpotlight);
			$interface->assign('showViewMoreLink', $collectionSpotlight->showViewMoreLink);
			if ($collectionSpotlightList->sourceListId != null && $collectionSpotlightList->sourceListId > 0){
				require_once ROOT_DIR . '/sys/UserLists/UserList.php';
				$sourceList = new UserList();
				$sourceList->id = $collectionSpotlightList->sourceListId;
				if ($sourceList->find(true)) {
					$result['listTitle'] = $sourceList->title;
					$result['listDescription'] = $sourceList->description;
					$result['titles'] = $sourceList->getSpotlightTitles( $collectionSpotlight);
					$currentIndex = 0;
					$result['currentIndex'] = $currentIndex;
				}
				$result['searchUrl'] = '/MyAccount/MyList/' . $collectionSpotlightList->sourceListId;
			}elseif ($collectionSpotlightList->sourceCourseReserveId != null && $collectionSpotlightList->sourceCourseReserveId > 0){
				require_once ROOT_DIR . '/sys/CourseReserves/CourseReserve.php';
				$sourceList = new CourseReserve();
				$sourceList->id = $collectionSpotlightList->sourceCourseReserveId;
				if ($sourceList->find(true)) {
					$result['listTitle'] = $sourceList->getTitle();
					$result['listDescription'] = '';
					$result['titles'] = $sourceList->getSpotlightTitles( $collectionSpotlight);
					$currentIndex = 0;
					$result['currentIndex'] = $currentIndex;
				}
				$result['searchUrl'] = '/CourseReserves/' . $collectionSpotlightList->sourceCourseReserveId;
			}else{
				$searchObject = $collectionSpotlightList->getSearchObject();

				$searchObject->processSearch();

				$result['listTitle'] = $collectionSpotlightList->name;
				$result['listDescription'] = '';
				$result['titles'] = $searchObject->getSpotlightResults($collectionSpotlight);
				$currentIndex = 0;
				$result['currentIndex'] = $currentIndex;
			}
			return $result;
		}else{
			return [
				'success' => false,
				'message' => 'Information for the carousel list could not be found',
			];
		}
	}

	/** @noinspection PhpUnused */
	function getEmailForm(){
		global $interface;
		return array(
			'title' => translate(['text' => 'Email Search', 'isPublicFacing' => true]),
			'modalBody' => $interface->fetch('Search/email.tpl'),
			'modalButtons' => "<span class='tool btn btn-primary' onclick='$(\"#emailSearchForm\").submit();'>". translate(['text' => "Send Email", 'isPublicFacing' => true]) . "</span>"
		);
	}

	/** @noinspection PhpUnused */
	function getDplaResults(){
		require_once ROOT_DIR . '/sys/SearchObject/DPLA.php';
		$dpla = new DPLA();
		$searchTerm = $_REQUEST['searchTerm'];
		if (!empty($searchTerm)){
			$results = $dpla->getDPLAResults($searchTerm);
			$formattedResults = $dpla->formatResults($results['records']);

			$returnVal = array(
				'rawResults' => $results['records'],
				'formattedResults' => $formattedResults,
			);
		}else{
			$returnVal = array(
				'rawResults' => [],
				'formattedResults' => '',
			);
		}

		//Format the results
		return $returnVal;
	}

	/** @noinspection PhpUnused */
	function getMoreSearchResults($displayMode = 'covers'){
		// Called Only for Covers mode //
		$success = true; // set to false on error

		if (isset($_REQUEST['view'])) $_REQUEST['view'] = $displayMode; // overwrite any display setting for now

		/** @var string $searchSource */
		$searchSource = !empty($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : 'local';

		// Initialise from the current search globals
		/** @var SearchObject_AbstractGroupedWorkSearcher $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init($searchSource);

		$searchObject->setLimit(24); // a set of 24 covers looks better in display

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if ($result instanceof AspenError) {
			AspenError::raiseError($result->getMessage());
			$success = false;
		}
		$searchObject->close();

		// Process for Display //
		$recordSet = $searchObject->getResultRecordHTML();
		$displayTemplate = 'Search/covers-list.tpl'; // structure for bookcover tiles

		// Rating Settings
		global $library;
		/** @var Location $locationSingleton */
		global $locationSingleton;
		$activeLocation = $locationSingleton->getActiveLocation();
		$browseCategoryRatingsMode = null;
		if ($activeLocation != null) {
			$browseCategoryRatingsMode = $activeLocation->getBrowseCategoryGroup()->browseCategoryRatingsMode;
		}else{
			$browseCategoryRatingsMode = $library->getBrowseCategoryGroup()->browseCategoryRatingsMode;
		}

		// when the Ajax rating is turned on, they have to be initialized with each load of the category.
		if ($browseCategoryRatingsMode == 1) $recordSet[] = '<script type="text/javascript">AspenDiscovery.Ratings.initializeRaters()</script>';

		global $interface;
		$interface->assign('recordSet', $recordSet);
		$records = $interface->fetch($displayTemplate);
		$result = array(
			'success' => $success,
			'records' => $records,
		);
		// let front end know if we have reached the end of the result set
		if ($searchObject->getPage() * $searchObject->getLimit() >= $searchObject->getResultTotal()) $result['lastPage'] = true;
		return $result;
	}

	/** @noinspection PhpUnused */
	function loadExploreMoreBar(){
		global $interface;

		$section = $_REQUEST['section'];
		$searchTerm = $_REQUEST['searchTerm'];
		if (is_array($searchTerm)){
			$searchTerm = reset($searchTerm);
		}
		$searchTerm = urldecode(html_entity_decode($searchTerm));

		//Load explore more data
		require_once ROOT_DIR . '/sys/ExploreMore.php';
		$exploreMore = new ExploreMore();
		$exploreMoreOptions = $exploreMore->loadExploreMoreBar($section, $searchTerm);
		if (count($exploreMoreOptions) == 0){
			$result = array(
					'success' => false,
			);
		}else{
			$result = array(
					'success' => true,
					'exploreMoreBar' => $interface->fetch("Search/explore-more-bar.tpl")
			);
		}

		return $result;
	}

	/** @noinspection PhpUnused */
	function lockFacet(){
		$response = [
			'success' => false,
			'message' => translate(['text'=>'Unknown Error', 'isPublicFacing'=>true])
		];
		$facetToLock = $_REQUEST['facet'];

		//Get the filters from the active search
		$searchObject = SearchObjectFactory::initSearchObject();
		/** @var SearchObject_BaseSearcher $activeSearch */
		$activeSearch = $searchObject->loadLastSearch();
		if (!is_null($activeSearch)) {
			//Save filters to the session or user object
			if (UserAccount::isLoggedIn()){
				$user = UserAccount::getActiveUserObj();
				$lockedFacets = !empty($user->lockedFacets) ? json_decode($user->lockedFacets, true) : [];
			}else{
				$lockedFacets = isset($_SESSION['lockedFilters']) ? $_SESSION['lockedFilters'] : [];
			}
			$lockedFacets[$facetToLock] = [];

			$lockSection = $activeSearch->getSearchName();
			$filters = $activeSearch->getFilterList();
			foreach ($filters as $appliedFacets){
				foreach ($appliedFacets as $appliedFacet){
					if ($appliedFacet['field'] == $facetToLock){
						$lockedFacets[$lockSection][$facetToLock][] = $appliedFacet['value'];
					}
				}
			}
			if (UserAccount::isLoggedIn()){
				$user = UserAccount::getActiveUserObj();
				$user->lockedFacets = json_encode($lockedFacets);
				$user->update();
			}else{
				$_SESSION['lockedFilters'] = $lockedFacets;
			}

			$response['success'] = true;
		}else{
			$response['message'] = 'Could not load search to lock filters for';
		}

		return $response;
	}

	/** @noinspection PhpUnused */
	function unlockFacet(){
		$response = [
			'success' => false,
			'message' => translate(['text'=>'Unknown Error', 'isPublicFacing'=>true])
		];

		//Get the filters from the active search
		$searchObject = SearchObjectFactory::initSearchObject();
		/** @var SearchObject_BaseSearcher $activeSearch */
		$activeSearch = $searchObject->loadLastSearch();
		$lockSection = $activeSearch->getSearchName();

		$facetToUnlock = $_REQUEST['facet'];
		if (UserAccount::isLoggedIn()){
			$user = UserAccount::getActiveUserObj();
			$lockedFacets = !empty($user->lockedFacets) ? json_decode($user->lockedFacets, true) : [];
		}else{
			$lockedFacets = isset($_SESSION['lockedFilters']) ? $_SESSION['lockedFilters'] : [];
		}
		if (isset($lockedFacets[$lockSection][$facetToUnlock])){
			unset($lockedFacets[$lockSection][$facetToUnlock]);
			if (UserAccount::isLoggedIn()){
				$user = UserAccount::getActiveUserObj();
				$user->lockedFacets = json_encode($lockedFacets);
				$user->update();
			}else{
				$_SESSION['lockedFilters'] = $lockedFacets;
			}
			$response['success'] = true;
		}else{
			$response['success'] = true;
			$response['message'] = 'That facet is already unlocked';
		}
		return $response;
	}

	function getSearchIndexes(){
		$searchSource = $_REQUEST['searchSource'];
		if ($searchSource == 'combined'){
			$response = [
				'success' => true,
				'searchIndexes' => ['Keyword' => translate(['text'=>'Keyword', 'isPublicFacing'=>true, 'inAttribute'=>true])],
				'selectedIndex' => 'Keyword',
				'defaultSearchIndex' => 'Keyword',
			];
		}else{
			$searchObject = SearchSources::getSearcherForSource($searchSource);
			if (!is_object($searchObject)){
				$response = [
					'success' => false,
					'message' => translate(['text'=>'Keyword', 'Unknown search source %1%', 1=> $searchSource, 'isPublicFacing'=>true, 'inAttribute'=>true])
				];
			}else{
				$searchIndexes = SearchSources::getSearchIndexesForSource($searchObject, $searchSource);
				$response = [
					'success' => true,
					'searchIndexes' => $searchIndexes,
					'selectedIndex' => $searchObject->getDefaultIndex(),
					'defaultSearchIndex' => $searchObject->getDefaultIndex(),
				];
			}
		}

		return $response;
	}

	function getBreadcrumbs() : array
	{
		return [];
	}
}
