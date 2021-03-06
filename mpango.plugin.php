<?php

class Mpango extends Plugin
{
	
	private static $projects = array();
	
	public function action_update_check()
	{
		Update::add( $this->info->name, 'e283ba9d-d16d-4932-b9dd-0117e84a3ba8', $this->info->version );
	}
	
	public function configure()
	{
		$ui = new FormUI( 'mpango_config' );
		$ui->append( 'text', 'github_id', 'mpango__github_id', _t('GitHub ID (optional)') );
		$ui->append( 'text', 'github_secret', 'mpango__github_secret', _t('GitHub Secret (optional)') );
		$ui->append('submit', 'save', _t('Save'));
		return $ui;
	}
	
	/**
	 * Set up needed stuff for the plugin
	 **/
	public function install()
	{
		Post::add_new_type( 'project' );
		
		// Give anonymous users access
		$group = UserGroup::get_by_name('anonymous');
		$group->grant('post_project', 'read');
	}
	
	/**
	 * Remove stuff we installed
	 **/
	public function uninstall()
	{
		Post::deactivate_post_type( 'project' );
	}
	
	/**
	 * action_plugin_activation
	 * @param string $file plugin file
	 */
	function action_plugin_activation( $file )
	{
		if( Plugins::id_from_file($file) == Plugins::id_from_file(__FILE__) ) {
			self::install();
		}
	}

	/**
	 * action_plugin_deactivation
	 * @param string $file plugin file
	 */
	function action_plugin_deactivation( $file )
	{
		if( Plugins::id_from_file($file) == Plugins::id_from_file(__FILE__) ) {
			self::uninstall();
		}
	}
	
	/**
	 * Create name string
	 **/
	public function filter_post_type_display($type, $foruse) 
	{ 
		$names = array( 
			'project' => array(
				'singular' => _t('Project'),
				'plural' => _t('Projects'),
			)
		); 
 		return isset($names[$type][$foruse]) ? $names[$type][$foruse] : $type; 
	}
	
	/**
	 * Modify publish form
	 */
	public function action_form_publish($form, $post)
	{
		if ( $post->content_type == Post::type('project') ) {
			
			$options = $form->publish_controls->append('fieldset', 'options', _t('Project'));
			
			$options->append('text', 'repository', 'null:null', _t('Repository URL'), 'tabcontrol_text');
			$options->repository->value = $post->info->repository;
			
			$options->append('text', 'commands', 'null:null', _t('Commands URL'), 'tabcontrol_text');
			$options->commands->value = $post->project->commands_url;
						
		}
	}
	
	/**
	 * Save our data to the database
	 */
	public function action_publish_post( $post, $form )
	{
		if ($post->content_type == Post::type('project')) {
			
			// $this->action_form_publish( $form, $post, 'create');
			
			$post->info->repository = $form->repository->value;
			$post->info->commands_url = $form->commands->value;
		
		}
	}
	
	/**
	 * Creates the Project class for each post
	 **/
	public function filter_post_project($project, $post) {
		if($post->content_type == Post::type('project')) {
			return $this->get_project( $post );		
		}
		else {
			return $project;
		}
	}
	
	/**
	 * Add needed elements to header
	 *
	 * 
	 **/
	public function action_template_header($theme)
	{
		if( $theme->request->display_post && $theme->post->project != null ) {
			if( $theme->post->project->type == 'ubiquity' ) {
				echo '<link rel="commands" href="' . $theme->post->project->commands_url . '" name="Ubiquity Commands" />';
			}
		}
	}
	
	/**
	 * Gets or creates the Project object 
	 **/
	public function get_project( $post )
	{
		if( isset( self::$projects[ $post->slug ] ) )
		{
			return self::$projects[ $post->slug ];
		}
		
		$project = new Project( $post );
		
		if( $post->info->repository != '' )
		{
			$repo = parse_url( $post->info->repository );
			if( $repo['host'] == 'github.com' )
			{
				$project = new GitHubProject( $post, $post->info->repository );
				
				$contents = $project->get_contents();
								
				foreach( $contents as $file )
				{
					if( $file->name == 'theme.xml' )
					{
						$project = new HabariGitHubProject( $post, $post->info->repository, 'theme' );
					}
					elseif( $file->name == $post->slug . '.plugin.xml' )
					{
						$project = new HabariGitHubProject( $post, $post->info->repository, 'plugin' );
					}
				}
								
			}
		}
		
		self::$projects[ $post->slug ] = $project;
		
		return $project;
	}
	
}


/**
* Class for projects
*/
class Project
{
	
	function __construct( $post )
	{
		$this->post = $post;
	}
	
	public function __get( $property ) {
		switch ( $property ) {
			case 'host':
				return false;
			case 'type':
				if( $this->xml != null ) {
					$this->type = (string) $this->xml['type'];
				}
				elseif( $this->commands_url != null )
					$this->type = 'ubiquity';
				else {
					$this->type = 'generic';
				}
				return $this->type;
			case 'xml_url':
				if( $this->repository == null ) {
					$this->xml_url = null;
				}
				else {
					$this->xml_url = $this->repository->trunk . $this->post->slug . '.plugin.xml';
				}
				
				return $this->xml_url;
			case 'commands_url':
				if( $this->post->info->commands_url == null ) {
					$this->commands_url = null;
				}
				else {
					$this->commands_url = $this->post->info->commands_url;
				}
				return $this->commands_url;
			case 'repository':
				if($this->post->info->repository == (null || false || '')) {
					$this->repository = null;
				}
				else {
					$repository = new stdClass;

					$repository->base = $this->post->info->repository;
					$repository->trunk = $repository->base . 'trunk/';

					$this->repository = $repository;
				}
				
				return $this->repository;
			case 'description':
				$this->description = (string) $this->xml->description;
				return $this->description;
			case 'version':
				$this->version = (string) $this->xml->version;
				return $this->version;
			case 'license':
				$this->license = array(
					'url' => (string) $this->xml->license['url'],
					'name' => (string) $this->xml->license
				);
				return $this->license;
			case 'authors':
				$authors = array();
				foreach( $this->xml->author as $author) {
					$authors[] = array(
						'url' => (string) $author['url'],
						'name' => (string) $author
					);
				}
								
				$this->authors = $authors;
				return $this->authors;
			case 'help':
				if( isset($this->xml->help) ) {
					foreach($this->xml->help->value as $help) {
						$this->help = (string) $help;
					}
				}
				else {
					$this->help = NULL;
				}
				return $this->help;
			default:
				if( isset( $this->{$property} ) )
				{
					return $this->{$property};
				}
		}
	}
	
}

/**
* Class for GitHubProjects
*/
class GitHubProject extends Project
{
	private $api = 'https://api.github.com';
	public $url;
	private $repodetails;
		
	function __construct( $post, $url )
	{		
		$this->post = $post;
		$this->url = parse_url( $url );
		
		$path = pathinfo( $this->url['path'] );
				
		$this->user = trim( $path['dirname'], '/');
		$this->repository = $path['basename'];
				
	}
		
	public function __get( $property )
	{
		switch( $property )
		{
			case 'host':
				return 'github';
			case 'tags':
				$response = $this->call( 'repos/' . $this->user . '/' . $this->repo . '/git/refs/tags' );
								
				$tags = array();

				if( is_array( $response ) )
				{
					foreach( $response as $bt )
					{					

						$tag = $this->call( 'repos/' . $this->user . '/' . $this->repo . '/git/tags/' . $bt->object->sha );

						$tag->basic = $bt;

						$tag->date = HabariDateTime::date_create( $tag->tagger->date );
						$tag->zipball_url = 'https://github.com/' . $this->user . '/' . $this->repo . '/zipball/' . $tag->tag;

						$tags[$tag->tag] = $tag;
					}
				}
				
				return array_reverse( $tags );
				
				break;
			
			case 'new_issue_url':
				return 'http://github.com/' . $this->user . '/' . $this->repo . '/issues/new';
			
			case 'zipball_url':
				return 'http://github.com/' . $this->user . '/' . $this->repo . '/zipball/master';
			
			case 'repo':
				return $this->repository;
				break; // will never be executed
			
			case 'github':
				return $this->html_url;
				return;
			
			case 'cloneurl':
				return $this->clone_url;
			
			case 'pushdate':
				return HabariDateTime::date_create( $this->pushed_at );
			
			case 'pushed_at':
			case 'html_url':
			case 'clone_url':
				if( !isset( $this->repodetails ) )
				{
					$details = $this->call( 'repos/' . $this->user . '/' . $this->repo );					
					$this->repodetails = $details;
					return $details->{$property};
				}
				else
				{
					return $this->repodetails->{$property};
				}
				break;
			
			default:
				return parent::__get( $property );
		}
	}
	
	private function call( $method, $cache = true )
	{
		if( $cache && Cache::has( array( 'mpango_githubapi', md5( $method ) ) ) )
		{
			return Cache::get( array( 'mpango_githubapi', md5( $method ) ) );
		}
		
		$url = $this->api . '/' . $method;
		
		if( Options::get( 'mpango__github_id' ) != null )
		{
			$url.= '?client_id=' . Options::get( 'mpango__github_id' ) . '&client_secret=' . Options::get( 'mpango__github_secret' );
		}
								
		$contents = RemoteRequest::get_contents( $url );		
										
		if( !$contents )
		{						
			return false;
		}
		
		$parsed = json_decode( $contents );
				
		Cache::set( array( 'mpango_githubapi', md5( $method ) ), $parsed );
		
		// Cache::set( array( 'mpango_githubapi', md5( $method ) ), $parsed );
				
		return $parsed;
	}
	
	public function get_contents( $path = '')
	{		
		$path = 'repos/' . $this->user . '/' . $this->repo . '/contents/' . $path;
		
		$response = $this->call( $path );
						
		if( !$response )
		{
			return false;
		}
				
		if( !is_array( $response ) )
		{			
			$response = $response->content;
			$response = base64_decode( $response );	
		}
						
		return $response;
	}
}


/**
* Class for HabariGitHubProjects
*/
class HabariGitHubProject extends GitHubProject
{
	private $xml;
		
	function __construct( $post, $url, $type )
	{
		parent::__construct( $post, $url );
		
		$this->type = $type;
		
		$this->xml();
				
	}
	
	private function xml()
	{
		if( $this->xml != null )
		{
			return $this->xml;
		}
		
		if( $this->type == 'theme' )
		{
			$xmlpath = 'theme.xml';
		}
		else
		{
			$xmlpath = $this->post->slug . '.plugin.xml';
		}
				
		$this->xml = simplexml_load_string( $this->get_contents( $xmlpath ) );
		
		return $this->xml;
	}
	
	public function __get( $property ) {
		switch ( $property ) {
			case 'platform':
				return 'habari';
			case 'description':
				$this->description = (string) $this->xml->description;
				return $this->description;
			case 'version':
				$this->version = (string) $this->xml->version;
				return $this->version;
			case 'license':
				$this->license = array(
					'url' => (string) $this->xml->license['url'],
					'name' => (string) $this->xml->license
				);
				return $this->license;
			case 'authors':
				$authors = array();
				foreach( $this->xml->author as $author) {
					$authors[] = array(
						'url' => (string) $author['url'],
						'name' => (string) $author
					);
				}
								
				$this->authors = $authors;
				return $this->authors;
			case 'help':
				if( isset($this->xml->help) ) {
					foreach($this->xml->help->value as $help) {
						$this->help = (string) $help;
					}
				}
				else {
					$this->help = NULL;
				}
				return $this->help;
			case 'screenshot_url':
				if( $this->type == 'theme')
				{
					return 'https://github.com/' . $this->user . '/' . $this->repo . '/raw/master/screenshot.jpg';
				}
				else
				{
					return null;
				}
				
			case 'xml':
				return $this->xml();
				
			default:
				return parent::__get( $property );
		}
	}
	
}

?>