<?php
namespace Politizr\FrontBundle\Lib\Xhr;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\GenericEvent;

use StudioEcho\Lib\StudioEchoUtils;

use Politizr\Exception\InconsistentDataException;
use Politizr\Exception\FormValidationException;

use Politizr\Constant\ObjectTypeConstants;
use Politizr\Constant\PathConstants;
use Politizr\Constant\TagConstants;
use Politizr\Constant\ListingConstants;

use Politizr\Model\PDDebate;
use Politizr\Model\PDReaction;
use Politizr\Model\PDDComment;
use Politizr\Model\PDRComment;

use Politizr\Model\PDDebateQuery;
use Politizr\Model\PDReactionQuery;
use Politizr\Model\PDDCommentQuery;
use Politizr\Model\PDRCommentQuery;

use Politizr\FrontBundle\Form\Type\PDDCommentType;
use Politizr\FrontBundle\Form\Type\PDRCommentType;
use Politizr\FrontBundle\Form\Type\PDDebateType;
use Politizr\FrontBundle\Form\Type\PDDebatePhotoInfoType;
use Politizr\FrontBundle\Form\Type\PDReactionType;
use Politizr\FrontBundle\Form\Type\PDReactionPhotoInfoType;

/**
 * XHR service for document management.
 *
 * @author Lionel Bouzonville
 */
class XhrDocument
{
    private $securityTokenStorage;
    private $securityAuthorizationChecker;
    private $kernel;
    private $session;
    private $eventDispatcher;
    private $templating;
    private $formFactory;
    private $router;
    private $userManager;
    private $documentManager;
    private $documentService;
    private $globalTools;
    private $logger;

    /**
     *
     * @param @security.token_storage
     * @param @security.authorization_checker
     * @param @kernel
     * @param @session
     * @param @event_dispatcher
     * @param @templating
     * @param @form.factory
     * @param @router
     * @param @politizr.manager.user
     * @param @politizr.manager.document
     * @param @politizr.functional.document
     * @param @politizr.tools.global
     * @param @logger
     */
    public function __construct(
        $securityTokenStorage,
        $securityAuthorizationChecker,
        $kernel,
        $session,
        $eventDispatcher,
        $templating,
        $formFactory,
        $router,
        $userManager,
        $documentManager,
        $documentService,
        $globalTools,
        $logger
    ) {
        $this->securityTokenStorage = $securityTokenStorage;
        $this->securityAuthorizationChecker = $securityAuthorizationChecker;

        $this->kernel = $kernel;
        $this->session = $session;

        $this->eventDispatcher = $eventDispatcher;

        $this->templating = $templating;
        $this->formFactory = $formFactory;
        $this->router = $router;

        $this->userManager = $userManager;
        $this->documentManager = $documentManager;
        $this->documentService = $documentService;

        $this->globalTools = $globalTools;

        $this->logger = $logger;
    }


    /* ######################################################################################################## */
    /*                                   FOLLOWING, NOTATION, COMMENTS                                          */
    /* ######################################################################################################## */

    /**
     * Follow/Unfollow a debate by current user
     */
    public function follow(Request $request)
    {
        $this->logger->info('*** follow');
        
        // Request arguments
        $uuid = $request->get('uuid');
        $this->logger->info('$uuid = ' . print_r($uuid, true));
        $way = $request->get('way');
        $this->logger->info('$way = ' . print_r($way, true));

        // Function process
        $user = $this->securityTokenStorage->getToken()->getUser();
        $debate = PDDebateQuery::create()->filterByUuid($uuid)->findOne();
        if ('follow' == $way) {
            $this->userManager->createUserFollowDebate($user->getId(), $debate->getId());

            // Events
            $event = new GenericEvent($debate, array('user_id' => $user->getId(),));
            $dispatcher = $this->eventDispatcher->dispatch('r_debate_follow', $event);
            $event = new GenericEvent($debate, array('author_user_id' => $user->getId(),));
            $dispatcher = $this->eventDispatcher->dispatch('n_debate_follow', $event);
        } elseif ('unfollow' == $way) {
            $this->userManager->deleteUserFollowDebate($user->getId(), $debate->getId());

            // Events
            $event = new GenericEvent($debate, array('user_id' => $user->getId(),));
            $dispatcher = $this->eventDispatcher->dispatch('r_debate_unfollow', $event);
        } else {
            throw new InconsistentDataException(sprintf('Follow\'s way %s not managed', $way));
        }

        // Rendering
        $html = $this->templating->render(
            'PolitizrFrontBundle:Follow:_subscribe.html.twig',
            array(
                'object' => $debate,
                'type' => ObjectTypeConstants::TYPE_DEBATE
            )
        );

        return array(
            'html' => $html,
            );
    }


    /**
     * Notation plus/minus of debate, reaction or comment
     * @todo refactoring
     */
    public function note(Request $request)
    {
        $this->logger->info('*** note');
        
        // Request arguments
        $uuid = $request->get('uuid');
        $this->logger->info('$uuid = ' . print_r($uuid, true));
        $type = $request->get('type');
        $this->logger->info('$type = ' . print_r($type, true));
        $way = $request->get('way');
        $this->logger->info('$way = ' . print_r($way, true));

        $user = $this->securityTokenStorage->getToken()->getUser();

        // Function process
        switch($type) {
            case ObjectTypeConstants::TYPE_DEBATE:
                $object = PDDebateQuery::create()->filterByUuid($uuid)->findOne();
                break;
            case ObjectTypeConstants::TYPE_REACTION:
                $object = PDReactionQuery::create()->filterByUuid($uuid)->findOne();
                break;
            case ObjectTypeConstants::TYPE_DEBATE_COMMENT:
                $object = PDDCommentQuery::create()->filterByUuid($uuid)->findOne();
                break;
            case ObjectTypeConstants::TYPE_REACTION_COMMENT:
                $object = PDRCommentQuery::create()->filterByUuid($uuid)->findOne();
                break;
        }

        // MAJ note
        if ('up' == $way) {
            $object->setNotePos($object->getNotePos() + 1);
            $object->save();

            // Events
            $event = new GenericEvent($object, array('user_id' => $user->getId(),));
            $dispatcher = $this->eventDispatcher->dispatch('r_note_pos', $event);
            $event = new GenericEvent($object, array('author_user_id' => $user->getId(),));
            $dispatcher = $this->eventDispatcher->dispatch('n_note_pos', $event);
            switch($type) {
                case ObjectTypeConstants::TYPE_DEBATE:
                case ObjectTypeConstants::TYPE_REACTION:
                    $event = new GenericEvent($object, array('author_user_id' => $user->getId(), 'target_user_id' => $object->getPUserId()));
                    $dispatcher = $this->eventDispatcher->dispatch('b_document_note_pos', $event);
                    break;
                case ObjectTypeConstants::TYPE_DEBATE_COMMENT:
                case ObjectTypeConstants::TYPE_REACTION_COMMENT:
                    $event = new GenericEvent($object, array('author_user_id' => $user->getId(), 'target_user_id' => $object->getPUserId()));
                    $dispatcher = $this->eventDispatcher->dispatch('b_comment_note_pos', $event);
                    break;
            }
        } elseif ('down' == $way) {
            $object->setNoteNeg($object->getNoteNeg() + 1);
            $object->save();

            // Events
            $event = new GenericEvent($object, array('user_id' => $user->getId(),));
            $dispatcher = $this->eventDispatcher->dispatch('r_note_neg', $event);
            $event = new GenericEvent($object, array('author_user_id' => $user->getId(),));
            $dispatcher = $this->eventDispatcher->dispatch('n_note_neg', $event);
            switch($type) {
                case ObjectTypeConstants::TYPE_DEBATE:
                case ObjectTypeConstants::TYPE_REACTION:
                    $event = new GenericEvent($object, array('author_user_id' => $user->getId(), 'target_user_id' => $object->getPUserId()));
                    $dispatcher = $this->eventDispatcher->dispatch('b_document_note_neg', $event);
                    break;
                case ObjectTypeConstants::TYPE_DEBATE_COMMENT:
                case ObjectTypeConstants::TYPE_REACTION_COMMENT:
                    $event = new GenericEvent($object, array('author_user_id' => $user->getId(), 'target_user_id' => $object->getPUserId()));
                    $dispatcher = $this->eventDispatcher->dispatch('b_comment_note_neg', $event);
                    break;
            }
        } else {
            throw new InconsistentDataException(sprintf('Notation\'s way %s not managed', $way));
        }

        // Rendering
        $html = $this->templating->render(
            'PolitizrFrontBundle:Reputation:_noteAction.html.twig',
            array(
                'object' => $object,
                'type' => $type,
            )
        );

        return array(
            'html' => $html
            );
    }

    /* ######################################################################################################## */
    /*                                              DEBATE EDITION                                              */
    /* ######################################################################################################## */

    /**
     * Debate update
     */
    public function debateUpdate(Request $request)
    {
        $this->logger->info('*** debateUpdate');
        
        // Request arguments
        $uuid = $request->get('debate')['uuid'];
        $this->logger->info('$uuid = ' . print_r($uuid, true));

        $user = $this->securityTokenStorage->getToken()->getUser();

        // Function process
        $debate = PDDebateQuery::create()->filterByUuid($uuid)->findOne();
        if (!$debate) {
            throw new InconsistentDataException('Debate '.$uuid.' not found.');
        }
        if (!$debate->isOwner($user->getId())) {
            throw new InconsistentDataException('Debate '.$uuid.' is not yours.');
        }
        if ($debate->getPublished()) {
            throw new InconsistentDataException('Debate '.$uuid.' is published and cannot be edited anymore.');
        }

        $form = $this->formFactory->create(new PDDebateType(), $debate);
        $form->bind($request);

        // No validator tests, always save
        $debate = $form->getData();
        $debate->save();

        return true;
    }

    /**
     * Update debate photo info
     */
    public function debatePhotoInfoUpdate(Request $request)
    {
        $this->logger->info('*** debatePhotoInfoUpdate');
        
        // Request arguments
        $uuid = $request->get('debate_photo_info')['uuid'];
        $this->logger->info('$uuid = ' . print_r($uuid, true));

        // Function process
        $user = $this->securityTokenStorage->getToken()->getUser();
        $debate = PDDebateQuery::create()->filterByUuid($uuid)->findOne();
        if (!$debate) {
            throw new InconsistentDataException('Debate '.$uuid.' not found.');
        }
        if (!$debate->isOwner($user->getId())) {
            throw new InconsistentDataException('Debate '.$uuid.' is not yours.');
        }
        if ($debate->getPublished()) {
            throw new InconsistentDataException('Debate '.$uuid.' is published and cannot be edited anymore.');
        }

        $form = $this->formFactory->create(new PDDebatePhotoInfoType(), $debate);

        // Retrieve actual file name
        $oldFileName = $debate->getFileName();

        $form->bind($request);
        if ($form->isValid()) {
            $debate = $form->getData();
            $debate->save();

            // Remove old file if new upload or deletion has been done
            $fileName = $debate->getFileName();
            if ($fileName != $oldFileName) {
                $path = $this->kernel->getRootDir() . '/../web' . PathConstants::DEBATE_UPLOAD_WEB_PATH;
                if ($oldFileName && $fileExists = file_exists($path . $oldFileName)) {
                    unlink($path . $oldFileName);
                }
            }
        } else {
            $errors = StudioEchoUtils::getAjaxFormErrors($form);
            throw new FormValidationException($errors);
        }

        // Rendering
        $path = 'bundles/politizrfront/images/default_debate.jpg';
        if ($fileName = $debate->getFileName()) {
            $path = PathConstants::DEBATE_UPLOAD_WEB_PATH.$fileName;
        }
        $imageHeader = $this->templating->render(
            'PolitizrFrontBundle:Document:_imageHeader.html.twig',
            array(
                'title' => $debate->getTitle(),
                'path' => $path,
                'filterName' => 'debate_header',
                'withShadow' => true
            )
        );

        return array(
            'imageHeader' => $imageHeader,
            'copyright' => $debate->getCopyright(),
            );
    }

    /**
     * Debate publication
     */
    public function debatePublish(Request $request)
    {
        $this->logger->info('*** debatePublish');
        
        // Request arguments
        $uuid = $request->get('uuid');
        $this->logger->info('$uuid = ' . print_r($uuid, true));

        // Function process
        $user = $this->securityTokenStorage->getToken()->getUser();
        $debate = PDDebateQuery::create()->filterByUuid($uuid)->findOne();
        if (!$debate) {
            throw new InconsistentDataException('Debate '.$uuid.' not found.');
        }
        if (!$debate->isOwner($user->getId())) {
            throw new InconsistentDataException('Debate '.$uuid.' is not yours.');
        }
        if ($debate->getPublished()) {
            throw new InconsistentDataException('Debate '.$uuid.' is published and cannot be edited anymore.');
        }

        // Validation
        $errorString = array();
        $valid = $this->globalTools->validateConstraints(
            array(
                'title' => $debate->getTitle(),
                // 'description' => strip_tags($debate->getDescription()),
                'geoTags' => $debate->getWorldToDepartmentGeoArrayTags(),
                'allTags' => $debate->getArrayTags(),
            ),
            $debate->getPublishConstraints(),
            $errorString
        );
        if (!$valid) {
            throw new FormValidationException($errorString);
        }

        // Publication
        $this->documentManager->publishDebate($debate);
        $this->session->getFlashBag()->add('success', 'Objet publié avec succès.');

        // Events
        $event = new GenericEvent($debate, array('user_id' => $user->getId(),));
        $dispatcher = $this->eventDispatcher->dispatch('r_debate_publish', $event);
        $event = new GenericEvent($debate, array('author_user_id' => $user->getId(),));
        $dispatcher = $this->eventDispatcher->dispatch('n_debate_publish', $event);

        return array(
            'redirectUrl' => $this->router->generate('MyPublications'.$this->globalTools->computeProfileSuffix()),
        );
    }

    /**
     * Debate deletion
     */
    public function debateDelete(Request $request)
    {
        $this->logger->info('*** debateDelete');
        
        // Request arguments
        $uuid = $request->get('uuid');
        $this->logger->info('$uuid = ' . print_r($uuid, true));

        // Function process
        $user = $this->securityTokenStorage->getToken()->getUser();
        $debate = PDDebateQuery::create()->filterByUuid($uuid)->findOne();
        if (!$debate) {
            throw new InconsistentDataException('Debate '.$uuid.' not found.');
        }
        if ($debate->getPublished()) {
            throw new InconsistentDataException('Debate '.$uuid.' is published and cannot be edited anymore.');
        }
        if (!$debate->isOwner($user->getId())) {
            throw new InconsistentDataException('Debate '.$uuid.' is not yours.');
        }

        $this->documentManager->deleteDebate($debate);
        $this->session->getFlashBag()->add('success', 'Objet supprimé avec succès.');

        return array(
            'redirectUrl' => $this->router->generate('Drafts'.$this->globalTools->computeProfileSuffix()),
        );
    }

    /* ######################################################################################################## */
    /*                                                  REACTION EDITION                                        */
    /* ######################################################################################################## */

    /**
     * Reaction update
     */
    public function reactionUpdate(Request $request)
    {
        $this->logger->info('*** reactionUpdate');
        
        // Request arguments
        $uuid = $request->get('reaction')['uuid'];
        $this->logger->info('$uuid = ' . print_r($uuid, true));

        // Function process
        $user = $this->securityTokenStorage->getToken()->getUser();
        $reaction = PDReactionQuery::create()->filterByUuid($uuid)->findOne();
        if (!$reaction) {
            throw new InconsistentDataException('Reaction '.$id.' not found.');
        }
        if (!$reaction->isOwner($user->getId())) {
            throw new InconsistentDataException('Reaction '.$id.' is not yours.');
        }
        if ($reaction->getPublished()) {
            throw new InconsistentDataException('Reaction '.$id.' is published and cannot be edited anymore.');
        }

        $form = $this->formFactory->create(new PDReactionType(), $reaction);
        $form->bind($request);

        // No validator tests, always save
        $reaction = $form->getData();
        $reaction->save();

        return true;
    }

    /**
     * Update reaction photo info
     */
    public function reactionPhotoInfoUpdate(Request $request)
    {
        $this->logger->info('*** reactionPhotoInfoUpdate');
        
        // Request arguments
        $uuid = $request->get('reaction_photo_info')['uuid'];
        $this->logger->info('$uuid = ' . print_r($uuid, true));

        // Function process
        $user = $this->securityTokenStorage->getToken()->getUser();
        $reaction = PDReactionQuery::create()->filterByUuid($uuid)->findOne();
        if (!$reaction) {
            throw new InconsistentDataException('Reaction '.$uuid.' not found.');
        }
        if (!$reaction->isOwner($user->getId())) {
            throw new InconsistentDataException('Reaction '.$uuid.' is not yours.');
        }
        if ($reaction->getPublished()) {
            throw new InconsistentDataException('Reaction '.$uuid.' is published and cannot be edited anymore.');
        }

        $form = $this->formFactory->create(new PDReactionPhotoInfoType(), $reaction);

        // Retrieve actual file name
        $oldFileName = $reaction->getFileName();

        $form->bind($request);
        if ($form->isValid()) {
            $reaction = $form->getData();
            $reaction->save();

            // Remove old file if new upload or deletion has been done
            $fileName = $reaction->getFileName();
            if ($fileName != $oldFileName) {
                $path = $this->kernel->getRootDir() . '/../web' . PathConstants::REACTION_UPLOAD_WEB_PATH;
                if ($oldFileName && $fileExists = file_exists($path . $oldFileName)) {
                    unlink($path . $oldFileName);
                }
            }
        } else {
            $errors = StudioEchoUtils::getAjaxFormErrors($form);
            throw new FormValidationException($errors);
        }

        // Rendering
        $path = 'bundles/politizrfront/images/default_reaction.jpg';
        if ($fileName = $reaction->getFileName()) {
            $path = PathConstants::REACTION_UPLOAD_WEB_PATH.$fileName;
        }
        $imageHeader = $this->templating->render(
            'PolitizrFrontBundle:Document:_imageHeader.html.twig',
            array(
                'title' => $reaction->getTitle(),
                'path' => $path,
                'filterName' => 'debate_header',
                'withShadow' => true
            )
        );

        return array(
            'imageHeader' => $imageHeader,
            'copyright' => $reaction->getCopyright(),
            );
    }

    /**
     * Reaction publication
     */
    public function reactionPublish(Request $request)
    {
        $this->logger->info('*** reactionPublish');
        
        // Request arguments
        $uuid = $request->get('uuid');
        $this->logger->info('$uuid = ' . print_r($uuid, true));

        // Function process
        $user = $this->securityTokenStorage->getToken()->getUser();
        $reaction = PDReactionQuery::create()->filterByUuid($uuid)->findOne();
        if (!$reaction) {
            throw new InconsistentDataException('Reaction '.$uuid.' not found.');
        }
        if (!$reaction->isOwner($user->getId())) {
            throw new InconsistentDataException('Reaction '.$uuid.' is not yours.');
        }
        if ($reaction->getPublished()) {
            throw new InconsistentDataException('Reaction '.$uuid.' is published and cannot be edited anymore.');
        }

        // Validation
        $errorString = array();
        $valid = $this->globalTools->validateConstraints(
            array(
                'title' => $reaction->getTitle(),
                // 'description' => strip_tags($reaction->getDescription()),
                'geoTags' => $reaction->getWorldToDepartmentGeoArrayTags(),
                'allTags' => $reaction->getArrayTags(),
            ),
            $reaction->getPublishConstraints(),
            $errorString
        );
        if (!$valid) {
            throw new FormValidationException($errorString);
        }

        // Publication
        $this->documentManager->publishReaction($reaction);
        $this->session->getFlashBag()->add('success', 'Objet publié avec succès.');

        // Events
        $parentUserId = $reaction->getDebate()->getPUserId();
        if ($reaction->getTreeLevel() > 1) {
            $parentUserId = $reaction->getParent()->getPUserId();
        }
        $event = new GenericEvent($reaction, array('user_id' => $user->getId(),));
        $dispatcher = $this->eventDispatcher->dispatch('r_reaction_publish', $event);
        $event = new GenericEvent($reaction, array('author_user_id' => $user->getId(),));
        $dispatcher = $this->eventDispatcher->dispatch('n_reaction_publish', $event);
        $event = new GenericEvent($reaction, array('author_user_id' => $user->getId(), 'parent_user_id' => $parentUserId));
        $dispatcher = $this->eventDispatcher->dispatch('b_reaction_publish', $event);

        // Renvoi de l'url de redirection
        return array(
            'redirectUrl' => $this->router->generate('MyPublications'.$this->globalTools->computeProfileSuffix()),
        );
    }


    /**
     * Reaction deletion
     */
    public function reactionDelete(Request $request)
    {
        $this->logger->info('*** reactionDelete');
        
        // Request arguments
        $uuid = $request->get('uuid');
        $this->logger->info('$uuid = ' . print_r($uuid, true));

        // Function process
        $user = $this->securityTokenStorage->getToken()->getUser();
        $reaction = PDReactionQuery::create()->filterByUuid($uuid)->findOne();
        if (!$reaction) {
            throw new InconsistentDataException('Reaction '.$uuid.' not found.');
        }
        if ($reaction->getPublished()) {
            throw new InconsistentDataException('Reaction '.$uuid.' is published and cannot be edited anymore.');
        }
        if (!$reaction->isOwner($user->getId())) {
            throw new InconsistentDataException('Reaction '.$uuid.' is not yours.');
        }

        $this->documentManager->deleteReaction($reaction);
        $this->session->getFlashBag()->add('success', 'Objet supprimé avec succès.');

        // Renvoi de l'url de redirection
        return array(
            'redirectUrl' => $this->router->generate('Drafts'.$this->globalTools->computeProfileSuffix()),
        );
    }

    /* ######################################################################################################## */
    /*                                 DEBATE & REACTION COMMON EDITION FUNCTIONS                               */
    /* ######################################################################################################## */

    /**
     * Document's photo upload
     */
    public function documentPhotoUpload(Request $request)
    {
        $this->logger->info('*** documentPhotoUpload');

        // Request arguments
        $uuid = $request->get('uuid');
        $this->logger->info(print_r($uuid, true));
        $type = $request->get('type');
        $this->logger->info(print_r($type, true));

        // Récupération débat courant
        $user = $this->securityTokenStorage->getToken()->getUser();
        switch ($type) {
            case ObjectTypeConstants::TYPE_DEBATE:
                $document = PDDebateQuery::create()->filterByUuid($uuid)->findOne();
                $uploadWebPath = PathConstants::DEBATE_UPLOAD_WEB_PATH;
                break;
            case ObjectTypeConstants::TYPE_REACTION:
                $document = PDReactionQuery::create()->filterByUuid($uuid)->findOne();
                $uploadWebPath = PathConstants::REACTION_UPLOAD_WEB_PATH;
                break;
            default:
                throw new InconsistentDataException('Object type not managed');
        }

        // Chemin des images
        $path = $this->kernel->getRootDir() . '/../web' . $uploadWebPath;

        // Appel du service d'upload ajax
        $fileName = $this->globalTools->uploadXhrImage(
            $request,
            'fileName',
            $path,
            1024,
            1024
        );

        // Rendering
        $html = $this->templating->render(
            'PolitizrFrontBundle:Document:_imageHeader.html.twig',
            array(
                'path' => $uploadWebPath . $fileName,
                'filterName' => 'debate_header',
                'title' => $document->getTitle(),
                'withShadow' => false
            )
        );

        return array(
            'fileName' => $fileName,
            'html' => $html,
            );
    }

    /* ######################################################################################################## */
    /*                                                  COMMENTS                                                */
    /* ######################################################################################################## */

    /**
     * Create a new comment
     */
    public function commentNew(Request $request)
    {
        $this->logger->info('*** commentNew');
        
        // Request arguments
        $type = $request->get('comment')['type'];
        $this->logger->info('$type = ' . print_r($type, true));
        $uuid = $request->get('uuid');
        $this->logger->info('$uuid = ' . print_r($uuid, true));

        // Function process
        $user = $this->securityTokenStorage->getToken()->getUser();
        switch ($type) {
            case ObjectTypeConstants::TYPE_DEBATE_COMMENT:
                $comment = new PDDComment();
                $document = PDDebateQuery::create()->filterByUuid($uuid)->findOne();
                $comment->setPDDebateId($document->getId());
                $comment->setOnline(true);
                $comment->setPUserId($user->getId());

                $commentNew = new PDDComment();
                $formType = new PDDCommentType();
                break;
            case ObjectTypeConstants::TYPE_REACTION_COMMENT:
                $comment = new PDRComment();
                $document = PDReactionQuery::create()->filterByUuid($uuid)->findOne();
                $comment->setOnline(true);
                $comment->setPUserId($user->getId());
                $comment->setPDReactionId($document->getId());

                $commentNew = new PDRComment();
                $formType = new PDRCommentType();
                break;
            default:
                throw new InconsistentDataException('Object type not managed');
        }

        $form = $this->formFactory->create($formType, $comment);

        $form->bind($request);
        if ($form->isValid()) {
            $this->logger->info('*** isValid');
            $comment = $form->getData();

            $comment->save();
        } else {
            $this->logger->info('*** not valid');
            $errors = StudioEchoUtils::getAjaxFormErrors($form);
            throw new FormValidationException($errors);
        }

        // Get associated object
        $document = $comment->getPDocument();
        $noParagraph = $comment->getParagraphNo();

        // New form creation
        $comments = $document->getComments(true, $noParagraph);

        if ($user) {
            $commentNew->setParagraphNo($noParagraph);
        }
        $form = $this->formFactory->create($formType, $comment);

        // Events
        $event = new GenericEvent($comment, array('user_id' => $user->getId(),));
        $dispatcher = $this->eventDispatcher->dispatch('r_comment_publish', $event);
        $event = new GenericEvent($comment, array('author_user_id' => $user->getId(),));
        $dispatcher = $this->eventDispatcher->dispatch('n_comment_publish', $event);
        $event = new GenericEvent($comment, array('author_user_id' => $user->getId()));
        $dispatcher = $this->eventDispatcher->dispatch('b_comment_publish', $event);

        // Rendering
        $html = $this->templating->render(
            'PolitizrFrontBundle:Comment:_paragraphComments.html.twig',
            array(
                'document' => $document,
                'comments' => $comments,
                'formComment' => $form->createView(),
            )
        );
        $counter = $this->templating->render(
            'PolitizrFrontBundle:Comment:_counter.html.twig',
            array(
                'document' => $document,
                'paragraphNo' => $noParagraph,
            )
        );

        return array(
            'html' => $html,
            'counter' => $counter,
            );
    }

    /**
     * Display comments & create form comment
     */
    public function comments(Request $request)
    {
        $this->logger->info('*** comments');
        
        // Request arguments
        $uuid = $request->get('uuid');
        $this->logger->info('$uuid = ' . print_r($uuid, true));
        $type = $request->get('type');
        $this->logger->info('$type = ' . print_r($type, true));
        $noParagraph = $request->get('noParagraph');
        $this->logger->info('$noParagraph = ' . print_r($noParagraph, true));

        // Function process
        $user = $this->securityTokenStorage->getToken()->getUser();
        switch ($type) {
            case ObjectTypeConstants::TYPE_DEBATE:
                $document = PDDebateQuery::create()->filterByUuid($uuid)->findOne();
                $comment = new PDDComment();
                $formType = new PDDCommentType();
                break;
            case ObjectTypeConstants::TYPE_REACTION:
                $document = PDReactionQuery::create()->filterByUuid($uuid)->findOne();
                $comment = new PDRComment();
                $formType = new PDRCommentType();
                break;
            default:
                throw new InconsistentDataException('Object type not managed');
        }

        $comments = $document->getComments(true, $noParagraph);

        if ($this->securityAuthorizationChecker->isGranted('ROLE_PROFILE_COMPLETED')) {
            $comment->setParagraphNo($noParagraph);
        }
        $formComment = $this->formFactory->create($formType, $comment);

        // Rendering
        $html = $this->templating->render(
            'PolitizrFrontBundle:Comment:_paragraphComments.html.twig',
            array(
                'document' => $document,
                'comments' => $comments,
                'formComment' => $formComment->createView(),
            )
        );
        $counter = $this->templating->render(
            'PolitizrFrontBundle:Comment:_counter.html.twig',
            array(
                'document' => $document,
                'paragraphNo' => $noParagraph,
            )
        );

        return array(
            'html' => $html,
            'counter' => $counter,
            );
    }

    /* ######################################################################################################## */
    /*                                                  DRAFTS                                                  */
    /* ######################################################################################################## */

    /**
     * User's drafts
     */
    public function draftsPaginated(Request $request)
    {
        $this->logger->info('*** draftsPaginated');

        // Request arguments
        $offset = $request->get('offset');
        $this->logger->info('$offset = ' . print_r($offset, true));

        // Function process
        $documents = $this->documentService->generateDraftsListing($offset);

        $moreResults = false;
        if (sizeof($documents) == ListingConstants::MODAL_CLASSIC_PAGINATION) {
            $moreResults = true;
        }

        if ($offset == 0 && count($documents) == 0) {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_noResult.html.twig',
                array(
                    'type' => ListingConstants::MY_DRAFTS_TYPE,
                )
            );
        } else {
            $html = $this->templating->render(
                'PolitizrFrontBundle:Document:_paginatedDrafts.html.twig',
                array(
                    'profileSuffix' => $this->globalTools->computeProfileSuffix(),
                    'documents' => $documents,
                    'offset' => intval($offset) + ListingConstants::MODAL_CLASSIC_PAGINATION,
                    'moreResults' => $moreResults,
                )
            );
        }

        return array(
            'html' => $html,
        );
    }

    /* ######################################################################################################## */
    /*                                            CONTRIBUTIONS                                                 */
    /* ######################################################################################################## */

    /**
     * User's publications
     */
    public function myPublicationsPaginated(Request $request)
    {
        $this->logger->info('*** myPublicationsPaginated');

        // Request arguments
        $offset = $request->get('offset');
        $this->logger->info('$offset = ' . print_r($offset, true));

        // Function process
        $documents = $this->documentService->generatePublicationsListing($offset);

        $moreResults = false;
        if (sizeof($documents) == ListingConstants::MODAL_CLASSIC_PAGINATION) {
            $moreResults = true;
        }

        if ($offset == 0 && count($documents) == 0) {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_noResult.html.twig',
                array(
                    'type' => ListingConstants::MY_PUBLICATIONS_TYPE,
                )
            );
        } else {
            $html = $this->templating->render(
                'PolitizrFrontBundle:Document:_paginatedPublications.html.twig',
                array(
                    'profileSuffix' => $this->globalTools->computeProfileSuffix(),
                    'documents' => $documents,
                    'offset' => intval($offset) + ListingConstants::MODAL_CLASSIC_PAGINATION,
                    'moreResults' => $moreResults,
                )
            );
        }

        return array(
            'html' => $html,
        );
    }
}