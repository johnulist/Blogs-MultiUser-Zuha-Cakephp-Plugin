<?php
class BlogPostsController extends BlogsAppController {

	public $allowedActions = array('latest');
	public $uses = 'Blogs.BlogPost';
	
	
	public function __construct($request = null, $response = null) {
		parent::__construct($request, $response);
		if (in_array('Recaptcha', CakePlugin::loaded())) { 
			$this->components[] = 'Recaptcha.Recaptcha'; 
		}
		if (in_array('Comments', CakePlugin::loaded())) { 
			$this->components['Comments.Comments'] = array('userModelClass' => 'User'); 
		}
	}

	public function beforeFilter() {
		parent::beforeFilter();
		$this->passedArgs['comment_view_type'] = 'threaded';
	}
	
/**
 * View method
 *
 * @todo 		Need to find a better way more reusable way to use recaptcha
 */
	public function view($id=null) {
		# temporary recaptcha placement
		if (!empty($this->request->data)) {
			if ($this->Recaptcha->verify()) {
		        // do something, save you data, login, whatever
		    } else {
		        // display the raw API error
		        $this->Session->setFlash($this->Recaptcha->error);
				$this->redirect($this->referer());
		    }
		}

		$blogPost = $this->BlogPost->find('first',array(
			'conditions' => array(
				'BlogPost.id' => $id,
				),
			'contain' => array(
				'Author'
				),
			));
		
		$this->paginate = array(
			'limit' => 5,
			'order' => array(
			),
		);


		$this->paginate = array('Comment' => array(
			'order' => array('Comment.created' => 'desc'),
			'recursive' => 0,
			'limit' => 5,
			'conditions'=>array('model' => 'Blogs.BlogPost')
		));
		$this->set('blogPost',$blogPost);
	}
	
/**
 * Add method
 */
	public function add($blogId = null) {
		$this->BlogPost->Blog->id = $blogId;
		if (!$this->BlogPost->Blog->exists()) {
			throw new NotFoundException(__('Invalid blog.'));
		}
		if(!empty($this->request->data)) {
			try {
				$this->BlogPost->add($this->request->data);
				$this->Session->setFlash('Blog Post Saved');
				$this->redirect(array('action' => 'view', $this->BlogPost->id));
			} catch (Exception $e) {
				$this->Session->setFlash($e->getMessage());
			}
		}
		$authors = $this->BlogPost->Author->find('list');
		if (in_array('Categories', CakePlugin::loaded())) {
			$categories = $this->BlogPost->Category->generateTreeList(array('Category.model' => 'BlogPost'));
		} else {
			$categories = null;
		}
		$statuses = $this->BlogPost->statusTypes();
		$blog = $this->BlogPost->Blog->find('first', array('conditions' => array('Blog.id' => $blogId)));
		$page_title_for_layout = __('Add Blog Post to %s', $blog['Blog']['title']);
		$this->set(compact('authors', 'blogId', 'categories', 'statuses', 'page_title_for_layout'));
	}
	
	
/**
 * Edit method
 */
	public function edit($id = null) {
		$this->BlogPost->id = $id;
		if (!$this->BlogPost->exists()) {
			throw new NotFoundException(__('Invalid post.'));
		}

		if(!empty($this->request->data)) {
			try {
				$this->BlogPost->add($this->request->data);
				$this->Session->setFlash('Blog Post Saved');
				$this->redirect(array('action' => 'view', $this->BlogPost->id));
			} catch (Exception $e) {
				$this->Session->setFlash($e->getMessage());
			}
		}
		
		$blogPost = $this->BlogPost->find('first',array(
			'conditions' => array(
				'BlogPost.id' => $id
				),
			'contain' => array(
				'Category',
				),
			));
		$this->request->data = $blogPost;
		# _viewVars
		if (in_array('Categories', CakePlugin::loaded())) {
			$categories = $this->BlogPost->Category->generateTreeList(array('Category.model' => 'BlogPost'));
			$this->set(compact('categories'));
		}
		if (in_array('Tags', CakePlugin::loaded())) {
			$tags = $this->BlogPost->Tag->Tagged->find('cloud', array('conditions' => array('Tagged.foreign_key' => $id)));
			$this->request->data['BlogPost']['tags'] = implode(', ', Set::extract('/Tag/name', $tags));
		}
		$authors = $this->BlogPost->Author->find('list');
		$statuses = $this->BlogPost->statusTypes();
		$page_title_for_layout = __('Edit %s', $blogPost['BlogPost']['title']);
		$this->set(compact('authors', 'statuses', 'page_title_for_layout'));
	}
	
	public function latest() {
		#$this->Project = ClassRegistry::init('Projects.Project'); #TODO: why is this necessary here?
		
		if(isset($this->request->params['named']['blog_id']) && isset($this->request->params['named']['limit'])) {

			  $options = array(
			  	'conditions' => array(
					'BlogPost.blog_id' => $this->request->params['named']['blog_id']
				),
				'order' => 'created DESC',
				'limit' => $this->request->params['named']['limit']
			  );

			  return $this->BlogPost->find('all', $options);

		}
	}

}