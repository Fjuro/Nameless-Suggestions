<?php
/*
 *	Made by Partydragen
 *  https://github.com/partydragen/Nameless-Suggestions
 *  https://partydragen.com/
 *
 *  Suggestions page
 */
 
if(!$user->isLoggedIn()){
    Redirect::to(URL::build('/login'));
    die();
}
 
// Always define page name for navbar
define('PAGE', 'suggestions');
$page_title = $suggestions_language->get('general', 'suggestions');

require_once(ROOT_PATH . '/core/templates/frontend_init.php');
$timeago = new Timeago(TIMEZONE);

require_once(ROOT_PATH . '/modules/Suggestions/classes/Suggestions.php');
$suggestions = new Suggestions();

if(Input::exists()){
	if(Token::check(Input::get('token'))){
		$errors = array();
		
		$validate = new Validate();
		$validation = $validate->check($_POST, array(
			'title' => array(
				'required' => true,
				'min' => 6,
				'max' => 128,
			),
			'content' => array(
				'required' => true,
				'min' => 6,
			)
		));
					
		if($validation->passed()){
			// Check if category exists
			$category = DB::getInstance()->query('SELECT id FROM nl2_suggestions_categories WHERE id = ? AND deleted = 0', array(htmlspecialchars(Input::get('category'))))->results();
			if(!count($category)) {
				$errors[] = 'Invalid Category';
			}
			
			if(!count($errors)) {
				$queries->create('suggestions', array(
					'user_id' => $user->data()->id,
					'updated_by' => $user->data()->id,
					'category_id' => $category[0]->id,
					'created' => date('U'),
					'last_updated' => date('U'),
					'title' => htmlspecialchars(Input::get('title')),
					'content' => htmlspecialchars(nl2br(Input::get('content'))),
				));
				
				$suggestion_id = $queries->getLastId();
				
				HookHandler::executeEvent('newSuggestion', array(
					'event' => 'newSuggestion',
					'username' => $user->getDisplayname(),
					'content' => 'New suggestion by ' . $user->getDisplayname(),
					'content_full' => str_replace('&nbsp;', '', strip_tags(htmlspecialchars_decode(Input::get('content')))),
					'avatar_url' => $user->getAvatar(null, 128, true),
					'title' => Output::getClean('#' . $suggestion_id . ' - ' . Input::get('title')),
					'url' => rtrim(Util::getSelfURL(), '/') . URL::build('/suggestions/view/' . $suggestion_id . '-' . Util::stringToURL(Output::getClean(Input::get('title'))))
				));
				
				Redirect::to(URL::build('/suggestions/view/' . $suggestion_id));
				die();
			}
		} else {
			foreach($validation->errors() as $error){
				if(strpos($error, 'is required') !== false){
					switch($error){
						case (strpos($error, 'title') !== false):
							$errors[] = 'You must enter a title';
						break;
						case (strpos($error, 'content') !== false):
							$errors[] = 'You must enter a content';
						break;
					}
				} else if(strpos($error, 'minimum') !== false){
					switch($error){
						case (strpos($error, 'title') !== false):
							$errors[] = 'The title must be a minimum of 6 characters';
						break;
						case (strpos($error, 'content') !== false):
							$errors[] = 'The content must be a minimum of 6 characters';
						break;
					}
				} else if(strpos($error, 'maximum') !== false){
					switch($error){
						case (strpos($error, 'title') !== false):
							$errors[] = 'The title must be a maximum of 128 characters';
						break;
					}
				}
			}
		}
	}
}

if(isset($errors) && count($errors))
	$smarty->assign('ERRORS', $errors);

$smarty->assign(array(
	'SUGGESTIONS' => $suggestions_language->get('general', 'suggestions'),
	'NEW_SUGGESTION' => $suggestions_language->get('general', 'new_suggestion'),
	'BACK' => $language->get('general', 'back'),
	'BACK_LINK' => URL::build('/suggestions/'),
	'TITLE' => ((isset($_POST['title']) && $_POST['title']) ? Output::getPurified(Input::get('title')) : ''),
	'CONTENT' => ((isset($_POST['content']) && $_POST['content']) ? Output::getPurified(Input::get('content')) : ''),
	'CATEGORY' => ((isset($_POST['category']) && $_POST['category']) ? Output::getPurified(Input::get('category')) : ''),
	'CATEGORIES' => $suggestions->getCategories(),
	'TOKEN' => Token::get(),
));

// Load modules + template
Module::loadPage($user, $pages, $cache, $smarty, array($navigation, $cc_nav, $mod_nav), $widgets);

$page_load = microtime(true) - $start;
define('PAGE_LOAD_TIME', str_replace('{x}', round($page_load, 3), $language->get('general', 'page_loaded_in')));

$template->addJSScript('$(\'.ui.search\')
  .search({
	type: \'category\',
	apiSettings: {
	  url: \'/suggestions/search_api/?q={query}\'
	},
	minCharacters: 3
  })
;');

$template->onPageLoad();
	
$smarty->assign('WIDGETS', $widgets->getWidgets());
	
require(ROOT_PATH . '/core/templates/navbar.php');
require(ROOT_PATH . '/core/templates/footer.php');
	
// Display template
$template->displayTemplate('suggestions/new.tpl', $smarty);