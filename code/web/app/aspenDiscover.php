<?php
# ****************************************************************************************************************************
# * Last Edit: May 3, 2021
# * - Shows discover functionality based on parameters
# *
# * 05-03-21: added shortname to handle json returns - CZ
# * 04-08-21: base version - CZ
# ****************************************************************************************************************************

# ****************************************************************************************************************************
# * include the helper file that holds the URL information by client
# ****************************************************************************************************************************
include_once 'config.php';

# ****************************************************************************************************************************
# * grab the passed location parameter, then find the path
# ****************************************************************************************************************************
$library      = $_GET['library'];
$locationInfo = urlPath($library);
$urlPath      = $locationInfo[0];
$shortname    = $locationInfo[1];

# ****************************************************************************************************************************
# * give the number of results to return from the search - needed to accomodate for the culling of Hoopla and Kanopy
# ****************************************************************************************************************************
$searchLimit = 100;

# ****************************************************************************************************************************
# * grab the parameters needed and clean it up ... need to default it to something too if there is nothing there
# ****************************************************************************************************************************
$browseCat = $_GET['limiter'];
if (empty($browseCat)) {
	//Get the first available browse category
	$browseCategories = $urlPath . '/API/SearchAPI?method=getActiveBrowseCategories&includeSubCategories=true';
	$jsonBrowseCat    = json_decode(file_get_contents($browseCategories), true);
	$firstBrowseCategory = null;
	foreach($jsonBrowseCat['result'] as $obj){
		if (strcmp($obj['source'], 'List') == 0) { continue; }
		if (count($obj['subCategories']) > 0) {
			foreach($obj['subCategories'] as $subCats){
				if (strcmp($subCats['source'], 'List') == 0) { continue; }
				$browseCatList['Items'][] = array('title' => $subCats['display_label'], 'reference' => $subCats['text_id']);
				if (empty($browseCat)){
					$browseCat = $subCats['text_id'];
				}
			}
		} else {
			$browseCatList['Items'][] = array('title' => $obj['display_label'], 'reference' => $obj['text_id']);
			if (empty($browseCat)){
				$browseCat = $obj['text_id'];
			}
		}
		if (!empty($browseCat)){
			break;
		}
	}
}

# ****************************************************************************************************************************
# * search link to the catalogue
# ****************************************************************************************************************************
$reportURL = $urlPath . '/API/SearchAPI?method=getBrowseCategoryInfo&textId=' . $browseCat . '&pageSize=' . $searchLimit;

# ****************************************************************************************************************************
# * run the report and grab the JSON
# ****************************************************************************************************************************
$jsonData = json_decode(file_get_contents($reportURL), true);

# ****************************************************************************************************************************
# * loop over results and massage
# * - help: https://stackoverflow.com/questions/6964403/parsing-json-with-php
# ****************************************************************************************************************************
foreach($jsonData['result']['records'] as $item) {
  $author      = $item['author_display'];
  
# ****************************************************************************************************************************
# * collection code may be empty - need to dummy it out just in case
# ****************************************************************************************************************************
  $ccode       = '';
  if (isset($item['collection_' . $shortname][0])) { $ccode = $item['collection_' . $shortname][0]; }

  $format      = $item['format_' . $shortname][0];
  $iconName    = $urlPath . "/bookcover.php?id=" . $item['id'] . "&size=medium&type=grouped_work";
  $id          = $item['id'];
  
# ****************************************************************************************************************************
# * clean up the summary to remove some of the &# codes
# ****************************************************************************************************************************
  $summary     = utf8_encode(trim(strip_tags($item['display_description'])));
  $summary     = str_replace('&#8211;', ' - ', $summary);
  $summary     = str_replace('&#8212;', ' - ', $summary);
  $summary     = str_replace('&#160;', ' ', $summary);
  
  $title       = ucwords($item['title_display']);
  unset($itemList);

# ****************************************************************************************************************************
# * need to parse over the bib records
# ****************************************************************************************************************************
  foreach($item['record_details'] as $itemRecords) {
    if (strpos($itemRecords, 'ils:') > -1 || strpos($itemRecords, 'overdrive:') > -1) {
      $itemListing = explode('|', $itemRecords);
	  
	  //if (! is_array($itemList)) {
	  if (! isset($itemList)) {
	    $itemList[] = array('type' => $itemListing[0], 'name' => $itemListing[1]);
	  } elseif (! in_array($itemListing[1], array_column($itemList, 'name'))) {
        $itemList[] = array('type' => $itemListing[0], 'name' => $itemListing[1]);
      }
	} 
  }
  
# ****************************************************************************************************************************
# * Build out results array ... ensure we have at least one item available
# ****************************************************************************************************************************
  if (! empty($itemList)) {
	if (count($itemList) > 0) {
	  $searchResults['Items'][] = array('title' => trim($title), 'author' => $author, 'image' => $iconName, 'format' => $format . ' - ' . $ccode, 'itemList' => $itemList, 'key' => $id, 'summary' => $summary); 
	}
  } 
}

# ****************************************************************************************************************************
# * Output to JSON
# ****************************************************************************************************************************
header('Content-Type: application/json');
echo json_encode($searchResults);