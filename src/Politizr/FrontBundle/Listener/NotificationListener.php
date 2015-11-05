<?php

namespace Politizr\FrontBundle\Listener;

use Symfony\Component\EventDispatcher\GenericEvent;

use Politizr\Constant\NotificationConstants;

use Politizr\Model\PUNotification;

use Politizr\Model\PUserQuery;
use Politizr\Model\PRBadgeQuery;

/**
 * Gestion des actions mettant à jour la réputation
 *
 * @author Lionel Bouzonville
 */
class NotificationListener
{

    protected $eventDispatcher;
    protected $logger;

    /**
     *
     * @param @event_dispatcher
     * @param @logger
     */
    public function __construct(
        $eventDispatcher,
        $logger
    ) {
        $this->eventDispatcher = $eventDispatcher;
        
        $this->logger = $logger;
    }

    /**
     * Attribution d'une note positive sur un document ou un commentaire.
     *
     * Notifications associées à gérer:
     * - Note positive sur un de vos documents ou commentaires
     *
     * @param GenericEvent
     */
    public function onNNotePos(GenericEvent $event)
    {
        $this->logger->info('*** onNNotePos');

        $subject = $event->getSubject();
        $authorUserId = $event->getArgument('author_user_id');

        $objectName = get_class($subject);
        $objectId = $subject->getId();

        // Document associé
        $targetUserId = $subject->getPUserId();

        switch($objectName) {
            case 'Politizr\Model\PDDebate':
            case 'Politizr\Model\PDReaction':
                $pNotificationId = NotificationConstants::ID_D_NOTE_POS;
                break;
            case 'Politizr\Model\PDDComment':
            case 'Politizr\Model\PDRComment':
                $pNotificationId = NotificationConstants::ID_D_C_NOTE_POS;
                break;
        }

        $puNotification = $this->insertPUNotification($targetUserId, $authorUserId, $pNotificationId, $objectName, $objectId);

        // Alerte email
        $event = new GenericEvent($puNotification);
        $dispatcher =  $this->eventDispatcher->dispatch('n_e_check', $event);
    }

    /**
     * Attribution d'une note négative sur un document ou un commentaire.
     *
     * Notifications associées à gérer:
     * - Note négative sur un de vos documents ou commentaires
     *
     * @param GenericEvent
     */
    public function onNNoteNeg(GenericEvent $event)
    {
        $this->logger->info('*** onNNoteNeg');

        $subject = $event->getSubject();
        $authorUserId = $event->getArgument('author_user_id');

        $objectName = get_class($subject);
        $objectId = $subject->getId();

        // Document associé
        $targetUserId = $subject->getPUserId();

        switch($objectName) {
            case 'Politizr\Model\PDDebate':
            case 'Politizr\Model\PDReaction':
                $pNotificationId = NotificationConstants::ID_D_NOTE_NEG;
                break;
            case 'Politizr\Model\PDDComment':
            case 'Politizr\Model\PDRComment':
                $pNotificationId = NotificationConstants::ID_D_C_NOTE_NEG;
                break;
        }

        $puNotification = $this->insertPUNotification($targetUserId, $authorUserId, $pNotificationId, $objectName, $objectId);

        // Alerte email
        $event = new GenericEvent($puNotification);
        $dispatcher =  $this->eventDispatcher->dispatch('n_e_check', $event);
    }

    /**
     * Publication d'un débat.
     *
     * Notifications associées à gérer:
     * - Un débat ou une réaction a été publié par un utilisateur suivi
     *
     * @param GenericEvent
     */
    public function onNDebatePublish(GenericEvent $event)
    {
        $this->logger->info('*** onNDebatePublish');

        $subject = $event->getSubject();
        $authorUserId = $event->getArgument('author_user_id');
        $objectName = get_class($subject);
        $objectId = $subject->getId();

        // Récupération de l'auteur du débat
        $authorUser = PUserQuery::create()->findPk($authorUserId);

        // Liste des users suivant l'auteur du document et souhaitant être notifié de ses publications
        $users = $authorUser->getNotifDebateFollowers();
        foreach ($users as $user) {
            $pNotificationId = NotificationConstants::ID_S_U_DEBATE_PUBLISH;
            $puNotification = $this->insertPUNotification($user->getId(), $authorUserId, $pNotificationId, $objectName, $objectId);

            // Alerte email
            $event = new GenericEvent($puNotification);
            $dispatcher =  $this->eventDispatcher->dispatch('n_e_check', $event);
        }

    }

    /**
     * Publication d'une réaction.
     *
     * Notifications associées à gérer:
     * - Une réaction a été publiée sur un de vos débats / une de vos réactions
     * - Une réaction a été publié sur un débat suivi
     * - Une réaction a été publié par un auteur suivi
     *
     * @param GenericEvent
     */
    public function onNReactionPublish(GenericEvent $event)
    {
        $this->logger->info('*** onNDebateReactionPublish');
        
        $subject = $event->getSubject();
        $authorUserId = $event->getArgument('author_user_id');
        $objectName = get_class($subject);
        $objectId = $subject->getId();

        // Débat associé à la réaction
        $debate = $subject->getPDDebate();
        $debateUserId = $debate->getPUserId();
        $pNotificationId = NotificationConstants::ID_D_D_REACTION_PUBLISH;

        // Notification = reaction published on my debate
        // don't notif if same user
        if ($debateUserId != $authorUserId) {
            $puNotification = $this->insertPUNotification($debateUserId, $authorUserId, $pNotificationId, $objectName, $objectId);

            // Alerte email
            $event = new GenericEvent($puNotification);
            $dispatcher =  $this->eventDispatcher->dispatch('n_e_check', $event);
        }

        // Réaction associée à la réaction
        if ($subject->getTreeLevel() > 1) {
            $parent = $subject->getParent();

            $targetUserId = $parent->getPUserId();
            $pNotificationId = NotificationConstants::ID_D_R_REACTION_PUBLISH;

            // Notification = reaction published on my reaction
            // don't notif if same user
            if ($targetUserId != $authorUserId) {
                $puNotification = $this->insertPUNotification($targetUserId, $authorUserId, $pNotificationId, $objectName, $objectId);

                // Alerte email
                $event = new GenericEvent($puNotification);
                $dispatcher =  $this->eventDispatcher->dispatch('n_e_check', $event);
            }
        }

        // Notification = reaction published on followed debate
        $users = $debate->getNotifReactionFollowers();
        foreach ($users as $user) {
            $pNotificationId = NotificationConstants::ID_S_D_REACTION_PUBLISH;
            $puNotification = $this->insertPUNotification($user->getId(), $authorUserId, $pNotificationId, $objectName, $objectId);

            // Alerte email
            $event = new GenericEvent($puNotification);
            $dispatcher =  $this->eventDispatcher->dispatch('n_e_check', $event);
        }

        // Get exlusion query for users already following debate
        $alreadyNotifiedUsersQuery = $this->getNotInPUserQuery($users);

        // Récupération de l'auteur du débat
        $authorUser = PUserQuery::create()->findPk($authorUserId);

        // Notification = reaction published by followed user
        $users = $authorUser->getNotifReactionFollowers($alreadyNotifiedUsersQuery);

        foreach ($users as $user) {
            $pNotificationId = NotificationConstants::ID_S_U_REACTION_PUBLISH;
            $puNotification = $this->insertPUNotification($user->getId(), $authorUserId, $pNotificationId, $objectName, $objectId);

            // Alerte email
            $event = new GenericEvent($puNotification);
            $dispatcher =  $this->eventDispatcher->dispatch('n_e_check', $event);
        }

    }

    /**
     * Publication d'un commentaire.
     *
     * Notifications associées à gérer:
     * - Un commentaire a été publié sur un de vos documents
     * - Un commentaire a été publié par un utilisateur suivi
     *
     * @param GenericEvent
     */
    public function onNCommentPublish(GenericEvent $event)
    {
        $this->logger->info('*** onNCommentPublish');

        $subject = $event->getSubject();
        $authorUserId = $event->getArgument('author_user_id');
        $pNotificationId = NotificationConstants::ID_D_COMMENT_PUBLISH;
        $objectName = get_class($subject);
        $objectId = $subject->getId();

        // Document associé
        $document = $subject->getPDocument();
        $targetUserId = $document->getPUserId();

        // Notification = comment published on my debate/reaction
        // don't notif if same user
        if ($targetUserId != $authorUserId) {
            $puNotification = $this->insertPUNotification($targetUserId, $authorUserId, $pNotificationId, $objectName, $objectId);

            // Alerte email
            $event = new GenericEvent($puNotification);
            $dispatcher =  $this->eventDispatcher->dispatch('n_e_check', $event);
        }

        // Récupération de l'auteur du débat
        $authorUser = PUserQuery::create()->findPk($authorUserId);

        // Liste des users suivant l'auteur du commentaire et souhaitant être notifié de ses publications
        $users = $authorUser->getNotifCommentFollowers();
        foreach ($users as $user) {
            $pNotificationId = NotificationConstants::ID_S_U_COMMENT_PUBLISH;
            $puNotification = $this->insertPUNotification($user->getId(), $authorUserId, $pNotificationId, $objectName, $objectId);

            // Alerte email
            $event = new GenericEvent($puNotification);
            $dispatcher =  $this->eventDispatcher->dispatch('n_e_check', $event);
        }
    }


    /**
     * Suivi d'un débat.
     *
     * Notifications associées à gérer:
     * - Un utilisateur suit un de vos débats
     *
     * @param GenericEvent
     */
    public function onNDebateFollow(GenericEvent $event)
    {
        $this->logger->info('*** onNDebateFollow');

        $subject = $event->getSubject();
        $authorUserId = $event->getArgument('author_user_id');
        $pNotificationId = NotificationConstants::ID_D_D_FOLLOWED;

        $objectName = get_class($subject);
        $objectId = $subject->getId();

        // Auteur du débat
        $targetUserId = $subject->getPUserId();

        $puNotification = $this->insertPUNotification($targetUserId, $authorUserId, $pNotificationId, $objectName, $objectId);

        // Alerte email
        $event = new GenericEvent($puNotification);
        $dispatcher =  $this->eventDispatcher->dispatch('n_e_check', $event);
    }


    /**
     * Suivi d'un profil.
     *
     * Notifications associées à gérer:
     * - Un utilisateur suit votre profil
     *
     * @param GenericEvent
     */
    public function onNUserFollow(GenericEvent $event)
    {
        $this->logger->info('*** onNUserFollow');

        $subject = $event->getSubject();
        $authorUserId = $event->getArgument('author_user_id');
        $pNotificationId = NotificationConstants::ID_U_FOLLOWED;
        
        $objectName = get_class($subject);
        $objectId = $subject->getId();

        // User suivi
        $targetUserId = $subject->getId();

        $puNotification = $this->insertPUNotification($targetUserId, $authorUserId, $pNotificationId, $objectName, $objectId);

        // Alerte email
        $event = new GenericEvent($puNotification);
        $dispatcher =  $this->eventDispatcher->dispatch('n_e_check', $event);
    }



    /**
     * Gain d'un débat.
     *
     * Notifications associées à gérer:
     * - Vous avez obtenu un badge
     *
     * @param GenericEvent
     */
    public function onNBadgeWin(GenericEvent $event)
    {
        $this->logger->info('*** onNBadgeWin');

        $subject = $event->getSubject();
        $pNotificationId = NotificationConstants::ID_U_BADGE;
        
        $targetUserId = $subject->getPUserId();
        $authorUserId = $targetUserId;

        // Récupération de l'objet badge gagné
        $badgeId = $subject->getPRBadgeId();
        $badge = PRBadgeQuery::create()->findPk($badgeId);
        $objectName = get_class($badge);
        $objectId = $badge->getId();

        $puNotification = $this->insertPUNotification($targetUserId, $authorUserId, $pNotificationId, $objectName, $objectId);

        // Alerte email
        $event = new GenericEvent($puNotification);
        $dispatcher =  $this->eventDispatcher->dispatch('n_e_check', $event);
    }


    // ******************************************************** //
    //                      Méthodes privées                    //
    // ******************************************************** //

    /**
     *
     * @param PropelCollection[PUser] $users
     * @return PUserQuery
     */
    private function getNotInPUserQuery($users)
    {
        $usersIds = array();
        $usersQuery = null;
        foreach ($users as $user) {
            $usersIds[] = $user->getId();
        }
        $usersQuery = PUserQuery::create()->filterById($usersIds, \Criteria::NOT_IN);

        return $usersQuery;
    }

    /**
     * Insertion en BDD
     *
     * @param $userId
     * @param $authorUserId
     * @param $notificationId
     * @param $objectName
     * @param $objectId
     *
     * @return PUNotification  Objet inséré
     */
    private function insertPUNotification($userId, $authorUserId, $notificationId, $objectName, $objectId)
    {
        $this->logger->info('*** insertPUNotification');
        $this->logger->info('userId = '.print_r($userId, true));
        $this->logger->info('authorUserId = '.print_r($authorUserId, true));
        $this->logger->info('notificationId = '.print_r($notificationId, true));
        $this->logger->info('objectName = '.print_r($objectName, true));
        $this->logger->info('objectId = '.print_r($objectId, true));

        $notif = new PUNotification();

        $notif->setPUserId($userId);
        $notif->setPNotificationId($notificationId);
        $notif->setPObjectName($objectName);
        $notif->setPObjectId($objectId);
        $notif->setPAuthorUserId($authorUserId);
        $notif->setChecked(false);
        
        $notif->save();

        return $notif;
    }
}
