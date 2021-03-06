<?php
App::uses('BlogsAppModel', 'Blogs.Model');
/**
 *@property Blog Blog
 *@property AppUser Author
 *@property Category Category
 *@property Tag Tag
 */
class BlogPost extends BlogsAppModel {

	public $name = "BlogPost";
	
	public $fullName = "Blogs.BlogPost"; //for the sake of comments plugin
        
 /**
  * Acts as
  * 
  * @var array
  */
    public $actsAs = array(
        'Optimizable',
        'Galleries.Mediable',
		'Users.Usable'
		);
	
	public $validate = array();

	public $belongsTo = array(
		'Author' => array(
			'className' => 'Users.User',
			'foreignKey' => 'author_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
			), 
		'Blog' => array(
			'className' => 'Blogs.Blog',
			'foreignKey' => 'blog_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
			),
		);
	
/**
 * Constructor
 * 
 */
	public function __construct($id = false, $table = null, $ds = null) {
		if(CakePlugin::loaded('Media')) {
			$this->actsAs[] = 'Media.MediaAttachable';
		}
		if (in_array('Tags', CakePlugin::loaded())) {
			$this->actsAs['Tags.Taggable'] = array('automaticTagging' => true, 'taggedCounter' => true);
			$this->hasAndBelongsToMany['Tag'] = array(
            	'className' => 'Tags.Tag',
	       		'joinTable' => 'tagged',
	            'foreignKey' => 'foreign_key',
	            'associationForeignKey' => 'tag_id',
	    		'conditions' => 'Tagged.model = "BlogPost"',
	    		// 'unique' => true,
		        );
		}
		if (in_array('Categories', CakePlugin::loaded())) {
			//break;	
			$this->hasAndBelongsToMany['Category'] = array(
            	'className' => 'Categories.Category',
	       		'joinTable' => 'categorized',
	            'foreignKey' => 'foreign_key',
	            'with' => 'Categories.Categorized',
	            'associationForeignKey' => 'category_id',
    			'conditions' => 'Categorized.model = "BlogPost"',
	    		// 'unique' => true,
		        );
		}
    	parent::__construct($id, $table, $ds);		
    }
	
	
	
/**
 * Before save
 * 
 * @return bool
 */
	public function beforeSave($options = array()) {
		
		if (!isset($this->data['BlogPost']['published']) || empty($this->data['BlogPost']['published'])) {
			$this->data['BlogPost']['published'] = date('Y-m-d');
			debug('Yay!');
		}
		
		return parent::beforeSave($options);
	}
	
	
/**
 * After save
 * 
 * @return null
 * @todo		Not the best way to handle this.  Would be cool if it were a callback or something.
 */
	public function afterSave($created) {		
		// use twitter behavior to update status about new post
		if ($created && in_array('Twitter', CakePlugin::loaded()) && in_array('Connections', CakePlugin::loaded())) {
			$body = $this->data['BlogPost']['title'] . ' http://'.$_SERVER['HTTP_HOST'].'/blogs/blog_posts/view/' . $this->id; 
			
			App::uses('Connect', 'Connections.Model');
			$Connect = new Connect;
			$twitter = $Connect->find('first', array(
				'conditions' => array(
					'Connect.user_id' => CakeSession::read('Auth.User.id'),
					),
				));
			$connect = unserialize($twitter['Connect']['value']);
			
			if (!empty($connect['oauth_token']) && !empty($connect['oauth_token_secret'])) {
				$this->Behaviors->load('Twitter.Twitter', array(
					'oauthToken' => $connect['oauth_token'], 
					'oauthTokenSecret' => $connect['oauth_token_secret'],
					));
				$this->updateStatus($body);
			}				
		}
	}
	
	
/**
 * Add method
 * 
 * @param array
 * @return bool
 */
	public function add($data) {
		$categoryData['Category'] = $data['Category'];
		unset($data['Category']);//quick fix to remove categories, causing to be saved twice
		if ($this->save($data)) {
			// this is how the categories data should look when coming in.
			if (isset($categoryData['Category']['Category'][0])) {
				$categorized = array('BlogPost' => array('id' => array($this->id)));
				foreach ($categoryData['Category']['Category'] as $catId) {
					$categorized['Category']['id'][] = $catId;
				}
				if ($this->Category->categorized($categorized, 'BlogPost')) {
					// do nothing, the return is at the bottom of this if
				} else {
					throw new Exception(__d('blogs', 'Blog post category save failed.'));
				}
				return true;
			}
		} else {	
			throw new Exception(__d('blogs', 'Blog post save failed.'));
		}
	}
	
/**
 * The publish status of a post
 *
 * @param null
 * @return array
 */
	public function statusTypes() {
		return array(
			'published' => 'Published',
			'draft' => 'Draft',
			'pending' => 'Pending Approval',
			);
	}

}
