<?php

class Mpango extends Plugin
{
	
	public function action_update_check()
	{
		Update::add( $this->info->name, 'e283ba9d-d16d-4932-b9dd-0117e84a3ba8', $this->info->version );
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
			if($post->project->repository != null) {
				$options->repository->value = $post->project->repository->base;
			}
			
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
			return new Project( $post );
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
	
}

/**
* Class for projects, is a subclass of post
*/
class Project
{
	
	function __construct( $post )
	{
		$this->post = $post;
	}
	
	public function __get( $property ) {
		switch ( $property ) {
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
			case 'xml':
				if( $this->xml_url == null ) {
					$this->xml = null;
				} else {
					$key = 'mpango_plugin_xml_' . $this->post->slug;
					if( Cache::has( $key ) ) {
						$raw = Cache::get( $key );
						$this->xml = new SimpleXMLElement( $raw );
					}
					else {
						$this->xml = simplexml_load_file( $this->xml_url );
						Cache::set( $key, $this->xml->asXML() );
					}
				}
				
				return $this->xml;
		}
	}
	
}


?>