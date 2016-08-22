<?php
namespace Politizr\FrontBundle\Lib\Xhr;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\GenericEvent;

use StudioEcho\Lib\StudioEchoUtils;

use Politizr\Exception\InconsistentDataException;
use Politizr\Exception\BoxErrorException;

use Politizr\Constant\XhrConstants;
use Politizr\Constant\ObjectTypeConstants;
use Politizr\Constant\PathConstants;
use Politizr\Constant\ListingConstants;
use Politizr\Constant\TagConstants;

use Politizr\Model\PDDebate;
use Politizr\Model\PDReaction;
use Politizr\Model\PDDComment;
use Politizr\Model\PDRComment;
use Politizr\Model\PUBookmarkDD;
use Politizr\Model\PUBookmarkDR;

use Politizr\Model\PDDebateQuery;
use Politizr\Model\PDReactionQuery;
use Politizr\Model\PDDCommentQuery;
use Politizr\Model\PDRCommentQuery;
use Politizr\Model\PTagQuery;
use Politizr\Model\PQOrganizationQuery;
use Politizr\Model\PUserQuery;
use Politizr\Model\PUBookmarkDDQuery;
use Politizr\Model\PUBookmarkDRQuery;

use Politizr\FrontBundle\Form\Type\PDDCommentType;
use Politizr\FrontBundle\Form\Type\PDRCommentType;
use Politizr\FrontBundle\Form\Type\PDDebateType;
use Politizr\FrontBundle\Form\Type\PDDebatePhotoInfoType;
use Politizr\FrontBundle\Form\Type\PDReactionType;
use Politizr\FrontBundle\Form\Type\PDReactionPhotoInfoType;

/**
 * XHR service for document management.
 * beta
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
    private $tagService;
    private $globalTools;
    private $documentTwigExtension;
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
     * @param @politizr.functional.tag
     * @param @politizr.tools.global
     * @param @politizr.twig.document
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
        $tagService,
        $globalTools,
        $documentTwigExtension,
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
        $this->tagService = $tagService;

        $this->globalTools = $globalTools;

        $this->documentTwigExtension = $documentTwigExtension;

        $this->logger = $logger;
    }


    /* ######################################################################################################## */
    /*                                   FOLLOWING, NOTATION, COMMENTS                                          */
    /* ######################################################################################################## */

    /**
     * Follow/Unfollow a debate by current user
     * beta
     */
    public function follow(Request $request)
    {
        // $this->logger->info('*** follow');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));
        $way = $request->get('way');
        // $this->logger->info('$way = ' . print_r($way, true));

        // get current user
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
            'PolitizrFrontBundle:Follow:_subscribeAction.html.twig',
            array(
                'subject' => $debate,
            )
        );

        return array(
            'html' => $html,
        );
    }

    /**
     * Notation plus/minus of debate, reaction or comment
     * beta
     * @todo refactoring
     */
    public function note(Request $request)
    {
        // $this->logger->info('*** note');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));
        $type = $request->get('type');
        // $this->logger->info('$type = ' . print_r($type, true));
        $way = $request->get('way');
        // $this->logger->info('$way = ' . print_r($way, true));

        $user = $this->securityTokenStorage->getToken()->getUser();

        // Function process
        switch($type) {
            case ObjectTypeConstants::TYPE_DEBATE:
                $subject = PDDebateQuery::create()->filterByUuid($uuid)->findOne();
                break;
            case ObjectTypeConstants::TYPE_REACTION:
                $subject = PDReactionQuery::create()->filterByUuid($uuid)->findOne();
                break;
            case ObjectTypeConstants::TYPE_DEBATE_COMMENT:
                $subject = PDDCommentQuery::create()->filterByUuid($uuid)->findOne();
                break;
            case ObjectTypeConstants::TYPE_REACTION_COMMENT:
                $subject = PDRCommentQuery::create()->filterByUuid($uuid)->findOne();
                break;
            default:
                throw new InconsistentDataException(sprintf('Note on type %s not allowed', $type));
        }

        // related to issue #178 > control user <> subject
        $canUserNoteDocument = $this->documentService->canUserNoteDocument($user, $subject, $way);
        if (!$canUserNoteDocument) {
            throw new InconsistentDataException('You can\'t note this publication.');
        }

        // update note
        if ('up' == $way) {
            $subject->setNotePos($subject->getNotePos() + 1);
            $subject->save();

            // Events
            $event = new GenericEvent($subject, array('user_id' => $user->getId(),));
            $dispatcher = $this->eventDispatcher->dispatch('r_note_pos', $event);
            $event = new GenericEvent($subject, array('author_user_id' => $user->getId(),));
            $dispatcher = $this->eventDispatcher->dispatch('n_note_pos', $event);
            switch($type) {
                case ObjectTypeConstants::TYPE_DEBATE:
                case ObjectTypeConstants::TYPE_REACTION:
                    $event = new GenericEvent($subject, array('author_user_id' => $user->getId(), 'target_user_id' => $subject->getPUserId()));
                    $dispatcher = $this->eventDispatcher->dispatch('b_document_note_pos', $event);
                    break;
                case ObjectTypeConstants::TYPE_DEBATE_COMMENT:
                case ObjectTypeConstants::TYPE_REACTION_COMMENT:
                    $event = new GenericEvent($subject, array('author_user_id' => $user->getId(), 'target_user_id' => $subject->getPUserId()));
                    $dispatcher = $this->eventDispatcher->dispatch('b_comment_note_pos', $event);
                    break;
            }
        } elseif ('down' == $way) {
            $subject->setNoteNeg($subject->getNoteNeg() + 1);
            $subject->save();

            // Events
            $event = new GenericEvent($subject, array('user_id' => $user->getId(),));
            $dispatcher = $this->eventDispatcher->dispatch('r_note_neg', $event);
            $event = new GenericEvent($subject, array('author_user_id' => $user->getId(),));
            $dispatcher = $this->eventDispatcher->dispatch('n_note_neg', $event);
            switch($type) {
                case ObjectTypeConstants::TYPE_DEBATE:
                case ObjectTypeConstants::TYPE_REACTION:
                    $event = new GenericEvent($subject, array('author_user_id' => $user->getId(), 'target_user_id' => $subject->getPUserId()));
                    $dispatcher = $this->eventDispatcher->dispatch('b_document_note_neg', $event);
                    break;
                case ObjectTypeConstants::TYPE_DEBATE_COMMENT:
                case ObjectTypeConstants::TYPE_REACTION_COMMENT:
                    $event = new GenericEvent($subject, array('author_user_id' => $user->getId(), 'target_user_id' => $subject->getPUserId()));
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
                'subject' => $subject
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
     * beta
     */
    public function debateUpdate(Request $request)
    {
        // $this->logger->info('*** debateUpdate');
        
        // Request arguments
        $uuid = $request->get('debate')['uuid'];
        // $this->logger->info('$uuid = ' . print_r($uuid, true));

        // get current user
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

        $form = $this->formFactory->create(new PDDebateType(), $debate);
        $form->bind($request);

        // No validator tests, always save
        $debate = $form->getData();
        $debate->save();

        return true;
    }

    /**
     * Debate publication
     * beta
     */
    public function debatePublish(Request $request)
    {
        // $this->logger->info('*** debatePublish');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));

        // get current user
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
                'geoTags' => $debate->getFranceToDepartmentGeoArrayTags(),
                'allTags' => $debate->getArrayTags(),
            ),
            $debate->getPublishConstraints(),
            $errorString
        );
        if (!$valid) {
            throw new BoxErrorException($errorString);
        }

        // Publication
        $this->documentManager->publishDebate($debate);
        $this->session->getFlashBag()->add('success', 'Objet publié avec succès.');

        // Events
        $event = new GenericEvent($debate, array('user_id' => $user->getId(),));
        $dispatcher = $this->eventDispatcher->dispatch('r_debate_publish', $event);
        $event = new GenericEvent($debate, array('author_user_id' => $user->getId(),));
        $dispatcher = $this->eventDispatcher->dispatch('n_debate_publish', $event);

        $redirectUrl = $this->router->generate('DebateDetail', array('slug' => $debate->getSlug()));

        return array(
            'redirectUrl' => $redirectUrl,
        );
    }

    /**
     * Debate deletion
     * beta
     */
    public function debateDelete(Request $request)
    {
        // $this->logger->info('*** debateDelete');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));

        // get current user
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
     * beta
     */
    public function reactionUpdate(Request $request)
    {
        // $this->logger->info('*** reactionUpdate');
        
        // Request arguments
        $uuid = $request->get('reaction')['uuid'];
        // $this->logger->info('$uuid = ' . print_r($uuid, true));

        // get current user
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

        $form = $this->formFactory->create(new PDReactionType(), $reaction);
        $form->bind($request);

        // No validator tests, always save
        $reaction = $form->getData();
        $reaction->save();

        return true;
    }

    /**
     * Reaction publication
     * beta
     */
    public function reactionPublish(Request $request)
    {
        // $this->logger->info('*** reactionPublish');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));

        // get current user
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
                'geoTags' => $reaction->getFranceToDepartmentGeoArrayTags(),
                'allTags' => $reaction->getArrayTags(),
            ),
            $reaction->getPublishConstraints(),
            $errorString
        );
        if (!$valid) {
            throw new BoxErrorException($errorString);
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

        $redirectUrl = $this->router->generate('ReactionDetail', array('slug' => $reaction->getSlug()));

        // Renvoi de l'url de redirection
        return array(
            'redirectUrl' => $redirectUrl,
        );
    }


    /**
     * Reaction deletion
     * beta
     */
    public function reactionDelete(Request $request)
    {
        // $this->logger->info('*** reactionDelete');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));

        // get current user
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
     * beta
     */
    public function documentPhotoUpload(Request $request)
    {
        // $this->logger->info('*** documentPhotoUpload');

        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info(print_r($uuid, true));
        $type = $request->get('type');
        // $this->logger->info(print_r($type, true));

        // get current user
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
                throw new InconsistentDataException(sprintf('Object type %s not managed', $type));
        }

        if (!$document->isOwner($user->getId())) {
            throw new InconsistentDataException('Document '.$uuid.' is not yours.');
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

        $document->setFileName($fileName);
        $html = $this->documentTwigExtension->image(
            $document,
            'debate_header'
        );

        return array(
            'fileName' => $fileName,
            'html' => $html,
        );
    }

    /**
     * Users's photo deletion
     * beta
     */
    public function documentPhotoDelete(Request $request)
    {
        // $this->logger->info('*** documentPhotoDelete');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info(print_r($uuid, true));
        $type = $request->get('type');
        // $this->logger->info(print_r($type, true));

        // get current user
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
                throw new InconsistentDataException(sprintf('Object type %s not managed', $type));
        }

        if (!$document->isOwner($user->getId())) {
            throw new InconsistentDataException('Document '.$uuid.' is not yours.');
        }

        // Chemin des images
        $path = $this->kernel->getRootDir() . '/../web' . $uploadWebPath;

        // Suppression photo déjà uploadée
        $filename = $document->getFilename();
        if ($filename && $fileExists = file_exists($path . $filename)) {
            unlink($path . $filename);
        }

        return true;
    }

    /* ######################################################################################################## */
    /*                                                  COMMENTS                                                */
    /* ######################################################################################################## */

    /**
     * Create a new comment
     * code beta
     */
    public function commentNew(Request $request)
    {
        // $this->logger->info('*** commentNew');
        
        // Request arguments
        $type = $request->get('comment')['type'];
        // $this->logger->info('$type = ' . print_r($type, true));
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));

        // get current user
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
                throw new InconsistentDataException(sprintf('Object type %s not managed', $type));
        }

        $form = $this->formFactory->create($formType, $comment);

        $form->bind($request);
        if ($form->isValid()) {
            // $this->logger->info('*** isValid');
            $comment = $form->getData();

            $comment->save();
        } else {
            // $this->logger->info('*** not valid');
            $errors = StudioEchoUtils::getAjaxFormErrors($form);
            throw new BoxErrorException($errors);
        }

        // Events
        $event = new GenericEvent($comment, array('user_id' => $user->getId(),));
        $dispatcher = $this->eventDispatcher->dispatch('r_comment_publish', $event);
        $event = new GenericEvent($comment, array('author_user_id' => $user->getId(),));
        $dispatcher = $this->eventDispatcher->dispatch('n_comment_publish', $event);
        $event = new GenericEvent($comment, array('author_user_id' => $user->getId()));
        $dispatcher = $this->eventDispatcher->dispatch('b_comment_publish', $event);

        return true;
    }

    /**
     * Display comments & create form comment
     * code beta
     */
    public function comments(Request $request)
    {
        // $this->logger->info('*** comments');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));
        $type = $request->get('type');
        // $this->logger->info('$type = ' . print_r($type, true));
        $noParagraph = $request->get('noParagraph');
        // $this->logger->info('$noParagraph = ' . print_r($noParagraph, true));

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
                throw new InconsistentDataException(sprintf('Object type %s not managed', $type));
        }

        $comments = $document->getComments(true, $noParagraph);

        if ($this->securityAuthorizationChecker->isGranted('ROLE_PROFILE_COMPLETED')) {
            $comment->setParagraphNo($noParagraph);
        }
        $formComment = $this->formFactory->create($formType, $comment);

        // Rendering
        $paragraphContext = 'global';
        if ($noParagraph > 0) {
            $paragraphContext = 'paragraph';
        }

        $html = $this->templating->render(
            'PolitizrFrontBundle:Comment:_list.html.twig',
            array(
                'paragraphContext' => $paragraphContext,
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
                'active' => true,
                'paragraphContext' => $paragraphContext,
            )
        );

        return array(
            'html' => $html,
            'counter' => $counter,
            );
    }

    /* ######################################################################################################## */
    /*                                            DRAFTS & BOOKMARKS                                            */
    /* ######################################################################################################## */

    /**
     * User's drafts
     * beta
     */
    public function myDraftsPaginated(Request $request)
    {
        // $this->logger->info('*** myDraftsPaginated');

        // Request arguments
        $offset = $request->get('offset');
        // $this->logger->info('$offset = ' . print_r($offset, true));

        // get current user
        $user = $this->securityTokenStorage->getToken()->getUser();

        // get drafts
        $documents = $this->documentService->getMyDraftsPaginatedListing($user->getId(), $offset, ListingConstants::LISTING_CLASSIC_PAGINATION);

        // @todo create function for code above
        $moreResults = false;
        if (sizeof($documents) == ListingConstants::LISTING_CLASSIC_PAGINATION) {
            $moreResults = true;
        }

        if ($offset == 0 && count($documents) == 0) {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_noResult.html.twig'
            );
        } else {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_drafts.html.twig',
                array(
                    'documents' => $documents,
                    'offset' => intval($offset) + ListingConstants::LISTING_CLASSIC_PAGINATION,
                    'moreResults' => $moreResults,
                    'jsFunctionKey' => XhrConstants::JS_KEY_LISTING_DOCUMENTS_BY_USER_DRAFTS
                )
            );
        }

        return array(
            'html' => $html,
        );
    }

    /**
     * User's bookmarks
     * beta
     */
    public function myBookmarksPaginated(Request $request)
    {
        // $this->logger->info('*** myBookmarksPaginated');

        // Request arguments
        $offset = $request->get('offset');
        // $this->logger->info('$offset = ' . print_r($offset, true));

        // get current user
        $user = $this->securityTokenStorage->getToken()->getUser();

        // get drafts
        $documents = $this->documentService->getMyBookmarksPaginatedListing($user->getId(), $offset, ListingConstants::LISTING_CLASSIC_PAGINATION);

        // @todo create function for code above
        $moreResults = false;
        if (sizeof($documents) == ListingConstants::LISTING_CLASSIC_PAGINATION) {
            $moreResults = true;
        }

        if ($offset == 0 && count($documents) == 0) {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_noResult.html.twig'
            );
        } else {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_documents.html.twig',
                array(
                    'documents' => $documents,
                    'offset' => intval($offset) + ListingConstants::LISTING_CLASSIC_PAGINATION,
                    'moreResults' => $moreResults,
                    'jsFunctionKey' => XhrConstants::JS_KEY_LISTING_DOCUMENTS_BY_USER_BOOKMARKS
                )
            );
        }

        return array(
            'html' => $html,
        );
    }

    /* ######################################################################################################## */
    /*                                          LISTING                                                         */
    /* ######################################################################################################## */

    /**
     * Most popular documents
     * code beta
     */
    public function topDocuments(Request $request)
    {
        // $this->logger->info('*** topDocuments');
        
        // Request arguments
        $filters = $request->get('documentFilterDate');
        // $this->logger->info('$filters = ' . print_r($filters, true));

        // @todo dynamic filters implementation
        $documents = $this->documentService->getTopDocumentsBestNote(
            ListingConstants::LISTING_TOP_DOCUMENTS_LIMIT
        );

        $html = $this->templating->render(
            'PolitizrFrontBundle:Document:_sidebarList.html.twig',
            array(
                'documents' => $documents
            )
        );

        return array(
            'html' => $html,
        );
    }

    /**
     * Suggestion documents
     * code beta
     */
    public function suggestionDocuments(Request $request)
    {
        // $this->logger->info('*** suggestionDocuments');
        
        // get current user
        $user = $this->securityTokenStorage->getToken()->getUser();
        
        $documents = $this->documentService->getUserDocumentsSuggestion($user->getId(), ListingConstants::LISTING_SUGGESTION_DOCUMENTS_LIMIT);
        // $documents = $this->documentService->getDocumentsLastPublished(ListingConstants::LISTING_SUGGESTION_DOCUMENTS_LIMIT);

        $html = $this->templating->render(
            'PolitizrFrontBundle:Document:_sliderSuggestions.html.twig',
            array(
                'documents' => $documents
            )
        );

        return array(
            'html' => $html,
        );
    }

    /**
     * Most recommended documents nav (prev/next computing)
     * code beta
     */
    public function documentsByRecommendNav(Request $request)
    {
        // $this->logger->info('*** documentsByRecommendNav');
        
        // Request arguments
        $numMonth = $request->get('month');
        // $this->logger->info('$numMonth = ' . print_r($numMonth, true));
        $year = $request->get('year');
        // $this->logger->info('$year = ' . print_r($year, true));

        $now = new \DateTime();
        $search = new \DateTime();
        $search->setDate($year, $numMonth, 1);

        if ($search > $now) {
            throw new InconsistentDataException('Cannot recommend with future date');
        }

        $month = $this->globalTools->getLabelFromMonthNum($numMonth);

        // next / prev
        $search->modify('-1 month');
        $prevNumMonth = $search->format('n');
        $prevMonth = $this->globalTools->getLabelFromMonthNum($prevNumMonth);
        $prevYear = $search->format('Y');
        $prevLink = $this->router->generate('ListingByRecommendMonthYear', array('month' => $prevMonth, 'year' => $prevYear));

        $search->modify('+2 month');
        $nextLink = null;
        $nextNumMonth = null;
        $nextYear = null;
        if ($search <= $now) {
            $nextNumMonth = $search->format('n');
            $nextMonth = $this->globalTools->getLabelFromMonthNum($nextNumMonth);
            $nextYear = $search->format('Y');
            $nextLink = $this->router->generate('ListingByRecommendMonthYear', array('month' => $nextMonth, 'year' => $nextYear));
        }

        $html = $this->templating->render(
            'PolitizrFrontBundle:Document:listingByRecommendNav.html.twig',
            array(
                'month' => $month,
                'numMonth' => $numMonth,
                'year' => $year,
                'prevLink' => $prevLink,
                'nextLink' => $nextLink,
                'prevNumMonth' => $prevNumMonth,
                'prevYear' => $prevYear,
                'nextNumMonth' => $nextNumMonth,
                'nextYear' => $nextYear,
            )
        );

        return array(
            'html' => $html,
            'month' => $month,
            'numMonth' => $numMonth,
            'year' => $year,
        );
    }

    /**
     * Most recommended documents
     * code beta
     */
    public function documentsByRecommend(Request $request)
    {
        // $this->logger->info('*** documentsByRecommend');
        
        // Request arguments
        $offset = $request->get('offset');
        // $this->logger->info('$offset = ' . print_r($offset, true));
        $month = $request->get('month');
        // $this->logger->info('$month = ' . print_r($month, true));
        $year = $request->get('year');
        // $this->logger->info('$year = ' . print_r($year, true));

        $documents = $this->documentService->getDocumentsByRecommendPaginated(
            $month,
            $year,
            $offset,
            ListingConstants::LISTING_CLASSIC_PAGINATION
        );

        // @todo create function for code above
        $moreResults = false;
        if (sizeof($documents) == ListingConstants::LISTING_CLASSIC_PAGINATION) {
            $moreResults = true;
        }

        if ($offset == 0 && count($documents) == 0) {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_noResult.html.twig'
            );
        } else {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_documents.html.twig',
                array(
                    'documents' => $documents,
                    'offset' => intval($offset) + ListingConstants::LISTING_CLASSIC_PAGINATION,
                    'moreResults' => $moreResults,
                    'jsFunctionKey' => XhrConstants::JS_KEY_LISTING_DOCUMENTS_BY_RECOMMEND
                )
            );
        }

        return array(
            'html' => $html,
        );
    }

    /**
     * Documents by tag
     * code beta
     */
    public function documentsByTag(Request $request)
    {
        // $this->logger->info('*** documentsByTag');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));
        $orderBy = $request->get('orderBy');
        // $this->logger->info('$orderBy = ' . print_r($orderBy, true));
        $offset = $request->get('offset');
        // $this->logger->info('$offset = ' . print_r($offset, true));

        // Retrieve subject
        $tag = PTagQuery::create()->filterByUuid($uuid)->findOne();
        if (!$tag) {
            throw new InconsistentDataException('Tag '.$uuid.' not found.');
        }

        // Compute relative geo tag ids
        $tagIds = $this->tagService->computeGeotagExtendedIds($tag->getId(), false, false);

        $documents = $this->documentService->getDocumentsByTagsPaginated(
            $tagIds,
            $orderBy,
            $offset,
            ListingConstants::LISTING_CLASSIC_PAGINATION
        );

        // @todo create function for code above
        $moreResults = false;
        if (sizeof($documents) == ListingConstants::LISTING_CLASSIC_PAGINATION) {
            $moreResults = true;
        }

        if ($offset == 0 && count($documents) == 0) {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_noResult.html.twig'
            );
        } else {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_documents.html.twig',
                array(
                    'uuid' => $uuid,
                    'documents' => $documents,
                    'offset' => intval($offset) + ListingConstants::LISTING_CLASSIC_PAGINATION,
                    'moreResults' => $moreResults,
                    'jsFunctionKey' => XhrConstants::JS_KEY_LISTING_DOCUMENTS_BY_TAG
                )
            );
        }

        return array(
            'html' => $html,
        );
    }

    /**
     * Documents tabs by organization
     * code beta
     */
    public function documentTabsByOrganization(Request $request)
    {
        // $this->logger->info('*** documentTabsByOrganization');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));

        // Retrieve subject
        $organization = PQOrganizationQuery::create()->filterByUuid($uuid)->findOne();
        if (!$organization) {
            throw new InconsistentDataException('Organization '.$uuid.' not found.');
        }

        $html = $this->templating->render(
            'PolitizrFrontBundle:Document:_documentTabsByOrganization.html.twig',
            array(
                'organization' => $organization,
            )
        );

        return array(
            'html' => $html,
        );
    }

    /**
     * Documents by organization
     * code beta
     */
    public function documentsByOrganization(Request $request)
    {
        // $this->logger->info('*** documentsByOrganization');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));
        $orderBy = $request->get('orderBy');
        // $this->logger->info('$orderBy = ' . print_r($orderBy, true));
        $offset = $request->get('offset');
        // $this->logger->info('$offset = ' . print_r($offset, true));

        // Retrieve subject
        $organization = PQOrganizationQuery::create()->filterByUuid($uuid)->findOne();
        if (!$organization) {
            throw new InconsistentDataException('Organization '.$uuid.' not found.');
        }

        $documents = $this->documentService->getDocumentsByOrganizationPaginated(
            $organization->getId(),
            $orderBy,
            $offset,
            ListingConstants::LISTING_CLASSIC_PAGINATION
        );

        // @todo create function for code above
        $moreResults = false;
        if (sizeof($documents) == ListingConstants::LISTING_CLASSIC_PAGINATION) {
            $moreResults = true;
        }

        if ($offset == 0 && count($documents) == 0) {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_noResult.html.twig'
            );
        } else {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_documents.html.twig',
                array(
                    'uuid' => $uuid,
                    'documents' => $documents,
                    'offset' => intval($offset) + ListingConstants::LISTING_CLASSIC_PAGINATION,
                    'moreResults' => $moreResults,
                    'jsFunctionKey' => XhrConstants::JS_KEY_LISTING_DOCUMENTS_BY_ORGANIZATION
                )
            );
        }

        return array(
            'html' => $html,
        );
    }

    /**
     * Publications by user
     * code beta
     */
    public function publicationsByUser(Request $request)
    {
        // $this->logger->info('*** publicationsByUser');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));
        $orderBy = $request->get('orderBy');
        // $this->logger->info('$orderBy = ' . print_r($orderBy, true));
        $offset = $request->get('offset');
        // $this->logger->info('$offset = ' . print_r($offset, true));


        $user = PUserQuery::create()->filterByUuid($uuid)->findOne();
        if (!$user) {
            throw new InconsistentDataException(sprintf('User %s not found', $uuid));
        }

        // get publications
        $publications = $this->documentService->getUserPublicationsPaginatedListing(
            $user->getId(),
            $orderBy,
            $offset,
            ListingConstants::LISTING_CLASSIC_PAGINATION
        );

        // @todo create function for code above
        $moreResults = false;
        if (sizeof($publications) == ListingConstants::LISTING_CLASSIC_PAGINATION) {
            $moreResults = true;
        }

        if ($offset == 0 && count($publications) == 0) {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_noResult.html.twig'
            );
        } else {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_publications.html.twig',
                array(
                    'uuid' => $uuid,
                    'publications' => $publications,
                    'offset' => intval($offset) + ListingConstants::LISTING_CLASSIC_PAGINATION,
                    'moreResults' => $moreResults,
                    'jsFunctionKey' => XhrConstants::JS_KEY_LISTING_PUBLICATIONS_BY_USER_PUBLICATIONS
                )
            );
        }

        return array(
            'html' => $html,
        );
    }

    /**
     * Filtered publications > reload filters
     * code beta
     */
    public function reloadFilters(Request $request)
    {
        // $this->logger->info('*** reloadFilters');

        // Request arguments
        $filterCategory = $request->get('filterCategory');
        // $this->logger->info('$filterCategory = ' . print_r($filterCategory, true));

        if ($filterCategory == ObjectTypeConstants::CONTEXT_PUBLICATION) {
            $template = '_publicationsCategory.html.twig';
        } elseif ($filterCategory == ObjectTypeConstants::CONTEXT_USER) {
            $template = '_usersCategory.html.twig';
        } else {
            throw new InconsistentDataException(sprintf('Filter category %s not managed', $filterCategory));
        }

        $html = $this->templating->render(
            'PolitizrFrontBundle:Search\\Filters:'.$template,
            array(
            )
        );

        return array(
            'html' => $html
        );
    }

    /**
     * Filtered publications
     * code beta
     */
    public function publicationsByFilters(Request $request)
    {
        // $this->logger->info('*** publicationsByFilters');
        
        // Request arguments
        $offset = $request->get('offset');
        // $this->logger->info('$offset = ' . print_r($offset, true));
        $geoTagUuid = $request->get('geoTagUuid');
        // $this->logger->info('$geoTagUuid = ' . print_r($geoTagUuid, true));
        $filterPublication = $request->get('filterPublication');
        // $this->logger->info('$filterPublication = ' . print_r($filterPublication, true));
        $filterProfile = $request->get('filterProfile');
        // $this->logger->info('$filterProfile = ' . print_r($filterProfile, true));
        $filterActivity = $request->get('filterActivity');
        // $this->logger->info('$filterActivity = ' . print_r($filterActivity, true));
        $filterDate = $request->get('filterDate');
        // $this->logger->info('$filterDate = ' . print_r($filterDate, true));

        // set default values if not set
        if (empty($geoTagUuid)) {
            $franceTag = PTagQuery::create()->findPk(TagConstants::TAG_GEO_FRANCE_ID);
            $geoTagUuid = $franceTag->getUuid();
        }
        if (empty($filterPublication)) {
            $filterPublication = ListingConstants::FILTER_KEYWORD_ALL_PUBLICATIONS;
        }
        if (empty($filterProfile)) {
            $filterProfile = ListingConstants::FILTER_KEYWORD_ALL_USERS;
        }
        if (empty($filterActivity)) {
            $filterActivity = ListingConstants::ORDER_BY_KEYWORD_LAST;
        }
        if (empty($filterDate)) {
            $filterDate = ListingConstants::FILTER_KEYWORD_ALL_DATE;
        }

        $publications = $this->documentService->getPublicationsByFilters(
            $geoTagUuid,
            $filterPublication,
            $filterProfile,
            $filterActivity,
            $filterDate,
            $offset,
            ListingConstants::LISTING_CLASSIC_PAGINATION
        );

        // @todo create function for code above
        $moreResults = false;
        if (sizeof($publications) == ListingConstants::LISTING_CLASSIC_PAGINATION) {
            $moreResults = true;
        }

        if ($offset == 0 && count($publications) == 0) {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_noResult.html.twig'
            );
        } else {
            $html = $this->templating->render(
                'PolitizrFrontBundle:PaginatedList:_publications.html.twig',
                array(
                    'publications' => $publications,
                    'offset' => intval($offset) + ListingConstants::LISTING_CLASSIC_PAGINATION,
                    'moreResults' => $moreResults,
                    'jsFunctionKey' => XhrConstants::JS_KEY_LISTING_PUBLICATIONS_BY_FILTERS
                )
            );
        }

        return array(
            'html' => $html,
        );
    }

    /* ######################################################################################################## */
    /*                                          DETAIL                                                          */
    /* ######################################################################################################## */

    /**
     * Bookmark/Unbookmark debate / reaction
     * code beta
     */
    public function bookmark(Request $request)
    {
        // $this->logger->info('*** bookmark');
        
        // Request arguments
        $uuid = $request->get('uuid');
        // $this->logger->info('$uuid = ' . print_r($uuid, true));
        $type = $request->get('type');
        // $this->logger->info('$type = ' . print_r($type, true));

        // get current user
        $user = $this->securityTokenStorage->getToken()->getUser();

        if ($type == ObjectTypeConstants::TYPE_DEBATE) {
            $document = PDDebateQuery::create()->filterByUuid($uuid)->findOne();

            $query = PUBookmarkDDQuery::create()
                ->filterByPDDebateId($document->getId())
                ->filterByPUserId($user->getId());

            $puBookmark = $query->findOne();
            if ($puBookmark) {
                // un-bookmark
                $query->filterByPUserId($user->getId())->delete();
            } else {
                // bookmark
                $bookmark = new PUBookmarkDD();
                $bookmark->setPUserId($user->getId());
                $bookmark->setPDDebateId($document->getId());

                $bookmark->save();
            }
        } elseif ($type == ObjectTypeConstants::TYPE_REACTION) {
            $document = PDReactionQuery::create()->filterByUuid($uuid)->findOne();

            $query = PUBookmarkDRQuery::create()
                ->filterByPDReactionId($document->getId())
                ->filterByPUserId($user->getId());
            
            $puBookmark = $query->findOne();
            if ($puBookmark) {
                // un-bookmark
                $query->filterByPUserId($user->getId())->delete();
            } else {
                // bookmark
                $bookmark = new PUBookmarkDR();
                $bookmark->setPUserId($user->getId());
                $bookmark->setPDReactionId($document->getId());

                $bookmark->save();
            }
        } else {
            throw new InconsistentDataException(sprintf('Object type %s not managed', $document->getType()));
        }

        $html = $this->templating->render(
            'PolitizrFrontBundle:Document:_bookmarkBoxDocument.html.twig',
            array(
                'document' => $document,
            )
        );

        return array(
            'html' => $html,
        );
    }

}
