<?php

class RegController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }

    public function indexAction()
    {
    	$form = new Application_Form_Register();
    	$form->setAction($this->view->url(array('controller' => 'reg')));
    	$this->view->form = $form;
    	if ($this->getRequest()->isPost()) {
    		$this->view->send = true;
    		if ($form->isValid($_POST)) {
    			$this->view->type = 1;
    			$this->view->text = $this->view->t->_("registrazione avvenuta con successo");
    			$post = $form->getValues();
    			$conf = Zend_Registry::get("config");
    			$code=$this->genrandpass();
    			$cryptcode = sha1($code);
    			$active=($conf->email->validation ? 0 : 1 );
    			$data = array('username' => $post['username'],
    					'password' => sha1($post['password']), 'email' => $post['email'],
    					'active' => $active, 'code' => $cryptcode);
    			Model_user::register($data);
    			if ($conf->email->validation) {
    				include_once APPLICATION_PATH.'/language/email.php';
    				$locale=$this->_t->getLocale();
    				$sender = new Zend_Mail();
    				$sender->addTo($post['email'])
    				->setFrom(WEBMAIL, SITO)
    				->setBodyHtml(
    						str_replace('{link}', $conf->url.$this->view->baseUrl('reg/active/code/'.$code),
    								str_replace('{user}', $post['username'], $message[$locale]['html'])))
    				->setBodyText(
    						str_replace('{link}', $conf->url.$this->view->baseUrl('reg/active/code/'.$code),
    								str_replace('{user}', $post['username'], $message[$locale]['text'])))
    				->setSubject($message[$locale]['obj'])
    				->send();
    			}
    		} else {
    			$this->view->type = 2;
    			$this->view->form->populate($_POST);
    		}
    	}
    }

    public function ctrlAction()
    {
    	
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        header("Content-type: application/json");
        $cerca=$this->_getParam('cerca');
        $value=$this->_getParam('valore');
        $db=new Zend_Validate_Db_NoRecordExists(array('table'=>PREFIX.'user','field'=>$cerca));
        switch ($cerca) {
        	case 'username':
        		$alnum= new Zend_Validate_Alnum();
        		$bool= (($alnum->isValid($value)) && ($db->isValid($value)));
        	break;
        	case 'email';
        		$email=new Zend_Validate_EmailAddress();
        		$bool= (($db->isValid($value)) && ($email->isValid($value)));
        	break;
        	default: $bool=true;
        }
        echo json_encode($bool);
    }
    public function activeAction() {
    	$code=$this->_getParam('code');
    	$code=$code ? sha1($code):null;
    	$user=new Model_user(0,$code);
    	$this->_log->debug(print_r($user->data,true));
    	if ($user->data && ($user->data->code_time+86400)<time()) {
    		$auth=Zend_Auth::getInstance();
    		$data=new stdClass();
    		$data->uid=$user->data->uid;
    		$data->username=$user->data->username;
    		$auth->getStorage()->write($data);
    		$this->view->text=$this->_t->_("SUC_ACTIV");
    		$user->updateU(array('active'=>1,'code'=>''));
    	}
    	else {
    		$this->view->text=$this->_t->_("ERR_ACTIV");
    		$this->view->type=2;
    	}
    }
    public function resendAction() {
    	include_once APPLICATION_PATH.'/language/email.php';
    	$locale=$this->_t->getLocale();
    	$user=new Model_user(0,$this->_getParam('code'));
    	if ($user->data) {
    		$this->view->email=$user->data['email'];
    		if ($this->getRequest()->isPost()) {
    			$valid=new Zend_Validate();
    			$valid->addValidator(new Zend_Validate_EmailAddress())->addValidator(new Zend_Validate_Db_NoRecordExists(array('table' => PREFIX.'user', 'field' => 'email')));
    			if ($valid->isValid($_POST['email']) || $_POST['email']==$user->data['email']) {
    				$code=$this->genrandpass();
    				$user->updateU(array('email'=>$_POST['email'],'code'=>sha1($code)));
    				$conf = Zend_Registry::get("config");
    				if ($conf->email->active) {
    					$sender = new Zend_Mail();
    					$sender->addTo($_POST['email'])
    					->setFrom(WEBMAIL, SITO)
    					->setBodyHtml(
    						str_replace('{link}', $conf->url.$this->view->baseUrl('reg/active/code/'.$code),
    								str_replace('{user}', $user->data['username'], $message[$locale]['html'])))
    					->setBodyText(
    						str_replace('{link}', $conf->url.$this->view->baseUrl('reg/active/code/'.$code),
    								str_replace('{user}', $$user->data['username'], $message[$locale]['text'])))
    					->setSubject($message[$locale]['obj'])
    					->send();
    				}
    				$this->view->text=$this->_t->_("CTRL_MAIL");
    				$this->view->succes=true;
    			}
    			else {
    				$this->view->error=true;
    				$this->view->text=$valid->getMessages();
    			}
    		}
    	}
    	else {
    		$this->view->error=true;
    		$this->view->text=$this->_t->_('ERRORE');
    	}
    }
    /**
     * generate random string 8 char lenght 
     * @return string
     */
    private function genrandpass() {
    	$code = "";
    	for ($i = 0; $i < 8; $i ++) {
    		$code .= (rand(0, 1) ? chr(rand(65, 122)) : rand(0, 9));
    	}
    	return $code;
    }
}



