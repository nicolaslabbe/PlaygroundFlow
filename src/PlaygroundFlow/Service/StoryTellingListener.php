<?php
namespace PlaygroundFlow\Service;

use Zend\Session\Container;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\EventManager\Event;
use ZfcBase\EventManager\EventProvider;
use Zend\ServiceManager\ServiceManagerAwareInterface;
use Zend\ServiceManager\ServiceManager;

/**
 * This listener is used to gather the stories from the managed domains
 *
 * @author Gregory Besson <gregory.besson@playground.gg>
 */
class StoryTellingListener extends EventProvider implements ListenerAggregateInterface, ServiceManagerAwareInterface
{

    /**
     *
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();

    protected $eventsArray = array();
    
    protected $serviceManager;

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events)
    {
        
        // PLAY A GAME
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), 'play.post', array(
            $this,
            'tellStoryAfter'
        ), 200);
        
        // GOOD ANSWERS ON A QUIZ
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), 'createQuizReply.post', array(
            $this,
            'tellStoryAfter'
        ), 200);
        
        // SHARE BY MAIL
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), 'sendShareMail.post', array(
            $this,
            'tellStoryAfter'
        ), 200);
        
        // SHARE ON FB WALL
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), 'postFbWall.post', array(
            $this,
            'tellStoryAfter'
        ), 200);
        
        // SHARE ON TWITTER
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), 'postTwitter.post', array(
            $this,
            'tellStoryAfter'
        ), 200);
        
        // SHARE ON GOOGLE
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), 'postGoogle.post', array(
            $this,
            'tellStoryAfter'
        ), 200);
        
        // REGISTER
        $this->listeners[] = $events->getSharedManager()->attach('PlaygroundUser\Service\User', 'register.post', array(
            $this,
            'tellStoryAfter'
        ), 200);
        
        // REGISTRATION SPONSORING
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), 'sponsor.post', array(
            $this,
            'tellStoryAfter'
        ), 200);
        
        // OPTIN
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), 'updateNewsletter.pre', array(
            $this,
            'tellStoryBefore'
        ), 200);
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), 'updateNewsletter.post', array(
            $this,
            'tellStoryAfter'
        ), 200);
        
        // OPTINPARTNER
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), 'updateNewsletterPartner.pre', array(
            $this,
            'tellStoryBefore'
        ), 201);
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), 'updateNewsletterPartner.post', array(
            $this,
            'tellStoryAfter'
        ), 201);
        
        // UPDATE ACCOUNT INFO
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), array(
            'updateInfo.pre'
        ), array(
            $this,
            'tellStoryBefore'
        ), 200);
        $this->listeners[] = $events->getSharedManager()->attach(array(
            '*'
        ), array(
            'updateInfo.post'
        ), array(
            $this,
            'tellStoryAfter'
        ), 200);
        
    }

    /**
     * {@inheritDoc}
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * differences :  RECHERCHE DE 1 OCCURENCE EXISTENTE
     * @param \Zend\EventManager\Event $e
     */
    public function sponsorAfter(\Zend\EventManager\Event $e)
    {
        $user = $e->getParam('user');
        $secretKey = $e->getParam('secretKey');
        
        $sm = $e->getTarget()->getServiceManager();
        $storyTellingService = $sm->get('playgroundflow_storytelling_service');
        
        $sponsorStory = $storyTellingService->getStoryTellingMapper()->findOneBySecretKey($secretKey);
        
        if ($sponsorStory) {
            $stories = $storyTellingService->getStoryMappingMapper()->findBy(array(
                'eventAfterUrl' => $e->getName()
            ));
            foreach ($stories as $story) {
                $object = array();
                $object['user'] = array(
                    'id' => $user->getId(),
                    'email' => $user->getEmail()
                );
                
                $storyTelling = new \PlaygroundFlow\Entity\OpenGraphStoryTelling();
                $storyTelling->setOpenGraphStoryMapping($story);
                $storyTelling->setUser($sponsorStory->getUser());
                $storyTelling->setObject(json_encode($object));
                $storyTelling->setPoints($story->getPoints());
                $storyTellingService->getStoryTellingMapper()->insert($storyTelling);
            }
        }
    }

    /**
     * differences : BEFORE !! et data et calcul
     * @param \Zend\EventManager\Event $e
     */
    public function newsletterBefore(\Zend\EventManager\Event $e)
    {
        $data = $e->getParam('data');
        $user = $e->getParam('user');
        
        $sm = $e->getTarget()->getServiceManager();
        $storyTellingService = $sm->get('playgroundflow_storytelling_service');
        /*
         * $logText = 'optin avant : ' . $user->getOptin() . 'apres : ' . $data['optin'] . '<br/>'; $logText .= 'optinPartner avant : ' . $user->getOptinPartner() . 'apres : ' . $data['optinPartner']. '<br/>'; $sm->get('jhu.zdt_logger')->info($logText);
         */
        
        // si avant !=1 et apres=1 => true
        if ($user->getOptin() != 1 && $data['optin'] == 1 && $e->getName() == 'updateNewsletter.pre') {
            $storiesMapping = $storyTellingService->getStoryMappingMapper()->findBy(array(
                'eventAfterUrl' => 'updateNewsletter.post'
            ));
            $nbStories = 0;
            foreach ($storiesMapping as $storyMapping) {
                $stories = $storyTellingService->getStoryTellingMapper()->findBy(array(
                    'openGraphStoryMapping' => $storyMapping,
                    'user' => $user
                ));
                $nbStories += count($stories);
            }
            
            if ($nbStories == 0) {
                $this->eventsArray['updateNewsletter.post'] = true;
            }
        }
        
        if ($user->getOptinPartner() != 1 && $data['optinPartner'] == 1 && $e->getName() == 'updateNewsletterPartner.pre') {
            $storiesMapping = $storyTellingService->getStoryMappingMapper()->findBy(array(
                'eventAfterUrl' => 'updateNewsletterPartner.post'
            ));
            $nbStories = 0;
            foreach ($storiesMapping as $storyMapping) {
                $stories = $storyTellingService->getStoryTellingMapper()->findBy(array(
                    'openGraphStoryMapping' => $storyMapping,
                    'user' => $user
                ));
                $nbStories += count($stories);
            }
            if ($nbStories == 0) {
                $this->eventsArray['updateNewsletterPartner.post'] = true;
            }
        }
    }

    /**
     * differences : je parcours une var en memoire
     * @param \Zend\EventManager\Event $e
     */
    public function newsletterAfter(\Zend\EventManager\Event $e)
    {
        $user = $e->getParam('user');
        $sm = $e->getTarget()->getServiceManager();
        $storyTellingService = $sm->get('playgroundflow_storytelling_service');
        
        if (isset($this->eventsArray[$e->getName()]) && $this->eventsArray[$e->getName()] === true) {
            // On compte les events
            $stories = $storyTellingService->getStoryMappingMapper()->findBy(array(
                'eventAfterUrl' => $e->getName()
            ));
            foreach ($stories as $story) {
                $object = array();
                $object['user'] = array(
                    'id' => $user->getId(),
                    'email' => $user->getEmail()
                );
                $storyTelling = new \PlaygroundFlow\Entity\OpenGraphStoryTelling();
                $storyTelling->setOpenGraphStoryMapping($story);
                $storyTelling->setUser($user);
                $storyTelling->setObject(json_encode($object));
                $storyTelling->setPoints($story->getPoints());
                $storyTellingService->getStoryTellingMapper()->insert($storyTelling);
            }
        }
        $this->eventsArray[$e->getName()] = false;
    }
    
    public function tellStoryBefore(\Zend\EventManager\Event $e)
    {

        $data = $e->getParam('data');
        $user = $e->getParam('user');
        $secretKey = $e->getParam('secretKey');
        
        $sm = $e->getTarget()->getServiceManager();
        $storyTellingService = $sm->get('playgroundflow_storytelling_service');
        
        // I reset the array before anything
        $this->eventsArray[$e->getName()] = null;
        
        $stories = $storyTellingService->getStoryMappingMapper()->findBy(array(
            'eventBeforeUrl' => $e->getName()
        ));
        
        foreach ($stories as $storyMapping) {
            $objectArray = array();
            foreach($storyMapping->getObjects() as $objectMapping){
                $objectCode = $objectMapping->getObject()->getCode();
                $instance = $e->getParam($objectCode);
                foreach($objectMapping->getAttributes() as $attributeMapping){
                    //echo "object : " . $objectMapping->getObject()->getCode() . "<br>";
                    //echo "object id : " . $objectMapping->getObject()->getId() . "<br>";
                    //echo "attribut : " . $attributeMapping->getAttribute()->getCode() . "<br>";
                    if( method_exists( $instance , $method = ( 'get' . ucfirst( $attributeMapping->getAttribute()->getCode() ) ) ) ){
                        if (isset($data[$attributeMapping->getAttribute()->getCode()]) && $instance->$method() != $data[$attributeMapping->getAttribute()->getCode()]) {
                            $this->eventsArray[$e->getName()]['before'][$objectCode][$attributeMapping->getAttribute()->getCode()] = $instance->$method();
                            $this->eventsArray[$e->getName()]['after'][$objectCode][$attributeMapping->getAttribute()->getCode()] = $data[$attributeMapping->getAttribute()->getCode()];
                        }
                    }
                }
            }
        }
    }
    
    /**
     *
     * @param Event $e
     */
    public function tellStoryAfter(\Zend\EventManager\Event $e)
    {
        $user = $e->getParam('user');
        $secretKey = $e->getParam('secretKey');
    
        $sm = $e->getTarget()->getServiceManager();
        $storyTellingService = $sm->get('playgroundflow_storytelling_service');
    
        // If the secretKey is not empty, I search th user associated with it as I want him to live the story
        if(!empty($secretKey)){
            $sponsorStory = $storyTellingService->getStoryTellingMapper()->findOneBySecretKey($secretKey);
            if ($sponsorStory) {
                $user = $sponsorStory->getUser();
            }
        }  
        
        $stories = $storyTellingService->getStoryMappingMapper()->findBy(array(
            'eventAfterUrl' => $e->getName()
        ));
        foreach ($stories as $storyMapping) {
            $objectArray = array();
            // an event before has been triggered
            $key = $storyMapping->getEventBeforeUrl();
            if(!empty($key) && isset($this->eventsArray[$key]) && $this->eventsArray[$key] !== null){
                $objectArray = $this->eventsArray[$key];
            } 
            // No before event triggered
            else{
                foreach($storyMapping->getObjects() as $objectMapping){
                    $objectCode = $e->getParam($objectMapping->getObject()->getCode());
                    foreach($objectMapping->getAttributes() as $attributeMapping){
                        //echo "object : " . $objectMapping->getObject()->getCode() . "<br>";
                        //echo "object id : " . $objectMapping->getObject()->getId() . "<br>";
                        //echo "attribut : " . $attributeMapping->getAttribute()->getCode() . "<br>";
                        if( method_exists( $objectCode , $method = ( 'get' . ucfirst( $attributeMapping->getAttribute()->getCode() ) ) ) ){
                            $objectArray[$objectMapping->getObject()->getCode()][$attributeMapping->getAttribute()->getCode()] = $objectCode->$method();
                        }
                    }
                }
            }
            $storyTelling = new \PlaygroundFlow\Entity\OpenGraphStoryTelling();
            $storyTelling->setOpenGraphStoryMapping($storyMapping);
            $storyTelling->setUser($user);
            $storyTelling->setObject(json_encode($objectArray));
            $storyTelling->setPoints($storyMapping->getPoints());
            $storyTelling->setSecretKey($secretKey);
            $storyTellingService->getStoryTellingMapper()->insert($storyTelling);
    
            $e->getTarget()->getEventManager()->trigger('story.'.$storyMapping->getId() , $this, array('storyTelling' => $storyTelling));
        }
    }
    
    /**
     * Retrieve service manager instance
     *
     * @return ServiceManager
     */
    public function getServiceManager ()
    {
        return $this->serviceManager;
    }
    
    /**
     * Set service manager instance
     *
     * @param  ServiceManager $sm
     * @return User
     */
    public function setServiceManager (ServiceManager $sm)
    {
        $this->serviceManager = $sm;
    
        return $this;
    }
}
