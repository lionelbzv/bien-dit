<?php

namespace Politizr\FrontBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Politizr\Model\PDDebateQuery;
use Politizr\Model\PDReactionQuery;
use Politizr\Model\PUserQuery;
use Politizr\Model\PUFollowDDQuery;
use Politizr\Model\PUFollowUQuery;

use Politizr\Model\PDDebate;
use Politizr\Model\PDReaction;
use Politizr\Model\PUser;
use Politizr\Model\PUFollowDD;
use Politizr\Model\PUFollowU;

/**
 * Gestion des documents: débats, réactions, commentaires.
 *
 * TODO:
 *  - 
 *
 * @author Lionel Bouzonville
 */
class DocumentController extends Controller {

    /* ######################################################################################################## */
    /*                                                 ROUTING CLASSIQUE                                        */
    /* ######################################################################################################## */

    /**
     * Détail débat
     */
    public function debateDetailAction($id, $slug)
    {
        $logger = $this->get('logger');
        $logger->info('*** debateDetailAction');
        $logger->info('$id = '.print_r($id, true));
        $logger->info('$slug = '.print_r($slug, true));

        // *********************************** //
        //      Récupération objet
        // *********************************** //
        $debate = PDDebateQuery::create()->findPk($id);
        if (!$debate) {
            throw new NotFoundHttpException('pDDebate n°'.$id.' not found.');
        }
        if (!$debate->getOnline()) {
            throw new NotFoundHttpException('pDDebate n°'.$id.' not online.');
        }

        // *********************************** //
        //      Affichage de la vue
        // *********************************** //
        return $this->render('PolitizrFrontBundle:Document:debateDetail.html.twig', array(
        			'debate' => $debate
        ));
    }

    /**
     * Détail auteur
     */
    public function authorDetailAction($id, $slug)
    {
        $logger = $this->get('logger');
        $logger->info('*** authorDetailAction');
        $logger->info('$id = '.print_r($id, true));
        $logger->info('$slug = '.print_r($slug, true));

        // *********************************** //
        //      Récupération objet
        // *********************************** //
        $pUser = PUserQuery::create()->findPk($id);
        if (!$pUser) {
            throw new NotFoundHttpException('pUser n°'.$id.' not found.');
        }
        if (!$pUser->getOnline()) {
            throw new NotFoundHttpException('pUser n°'.$id.' not online.');
        }

        // *********************************** //
        //      Récupération des objets associés
        // *********************************** //

        // PDDebate (collection)
        $debates = $pUser->getDebates();

        // PDReaction (collection)
        $reactions = $pUser->getReactions();

        // *********************************** //
        //      Affichage de la vue
        // *********************************** //
        return $this->render('PolitizrFrontBundle:Document:authorDetail.html.twig', array(
                    'pUser' => $pUser,
                    'debates' => $debates,
                    'reactions' => $reactions
            ));
    }

    /* ######################################################################################################## */
    /*                                                  FONCTIONS AJAX                                          */
    /* ######################################################################################################## */

    /**
     *      Renvoit le contenu complet d'un debat ou reaction
     */
    public function getDescriptionPDocumentAction(Request $request) {
        $logger = $this->get('logger');
        $logger->info('*** getDescriptionPDocumentAction');
        
        try {
            if ($request->isXmlHttpRequest()) {
                // Récupération args
                $id = $request->get('objectId');
                $logger->info('$id = ' . print_r($id, true));
                $type = $request->get('type');
                $logger->info('$type = ' . print_r($type, true));

                if ($type == 'debate') {
                    $pDocument = PDDebateQuery::create()->findPk($id);
                    if (!$pDocument) {
                        throw new NotFoundHttpException('pDDebate n°'.$id.' not found.');
                    }
                    if (!$pDocument->getOnline()) {
                        throw new NotFoundHttpException('pDDebate n°'.$id.' not online.');
                    }
                } else {
                    $pDocument = PDReactionQuery::create()->findPk($id);
                    if (!$pDocument) {
                        throw new NotFoundHttpException('pDReaction n°'.$id.' not found.');
                    }
                    if (!$pDocument->getOnline()) {
                        throw new NotFoundHttpException('pDReaction n°'.$id.' not online.');
                    }
                }

                // Construction du rendu du tag
                $templating = $this->get('templating');
                $htmlZen = $templating->render(
                                    'PolitizrFrontBundle:Fragment:DocumentZen.html.twig', array(
                                        'pDocument' => $pDocument
                                        )
                            );
                
                // Construction de la réponse
                $jsonResponse = array (
                    'success' => true,
                    'htmlZen' => $htmlZen
                );
            } else {
                throw $this->createNotFoundException('Not a XHR request');
            }
        } catch (NotFoundHttpException $e) {
            $logger->info('Exception = ' . print_r($e->getMessage(), true));
            $jsonResponse = array('error' => $e->getMessage());
        } catch (\Exception $e) {
            $logger->info('Exception = ' . print_r($e->getMessage(), true));
            $jsonResponse = array('error' => $e->getMessage());
        }

        // JSON formatted success/error message
        $response = new Response(json_encode($jsonResponse));
        return $response;
    }

    /**
     *      Suivi d'un débat / profil
     */
    public function followAction(Request $request) {
        $logger = $this->get('logger');
        $logger->info('*** followAction');
        
        try {
            if ($request->isXmlHttpRequest()) {
                // Récupération user
                $pUser = $this->getUser();
                if (!$pUser) {
                    throw new NotFoundHttpException('Utilisateur déconnecté.');
                }

                // Récupération args
                $objectId = $request->get('objectId');
                $logger->info('$objectId = ' . print_r($objectId, true));
                $objectType = $request->get('objectType');
                $logger->info('$objectType = ' . print_r($objectType, true));

                if ($objectType == 'debate') {
                    // TODO > contrôle élément non existant?

                    // Insertion nouvel élément
                    $pUFollowDD = new PUFollowDD();

                    $pUFollowDD->setPUserId($pUser->getId());
                    $pUFollowDD->setPDDebateId($objectId);

                    $pUFollowDD->save();
                } elseif ($objectType = 'puser') {
                    // TODO > contrôle élément non existant? / a priori exception dans ce cas

                    // Insertion nouvel élément
                    $pUFollowU = new PUFollowU();

                    $pUFollowU->setPUserId($objectId);
                    $pUFollowU->setPUserFollowerId($pUser->getId());

                    $pUFollowU->save();
                }

                // Construction rendu
                $templating = $this->get('templating');
                $html = $templating->render(
                                    'PolitizrFrontBundle:Fragment:FollowAction.html.twig', array(
                                        'objectId' => $objectId,
                                        'objectType' => $objectType,
                                        'isFollower' => true
                                        )
                            );


                // Construction de la réponse
                $jsonResponse = array (
                    'success' => true,
                    'html' => $html
                );
            } else {
                throw $this->createNotFoundException('Not a XHR request');
            }
        } catch (NotFoundHttpException $e) {
            $logger->info('Exception = ' . print_r($e->getMessage(), true));
            $jsonResponse = array('error' => $e->getMessage());
        } catch (\Exception $e) {
            $logger->info('Exception = ' . print_r($e->getMessage(), true));
            $jsonResponse = array('error' => $e->getMessage());
        }

        // JSON formatted success/error message
        $response = new Response(json_encode($jsonResponse));
        return $response;
    }

    /**
     *      Arrêter le suivi d'un débat / profil
     */
    public function unfollowAction(Request $request) {
        $logger = $this->get('logger');
        $logger->info('*** unfollowAction');
        
        try {
            if ($request->isXmlHttpRequest()) {
                // Récupération user
                $pUser = $this->getUser();
                if (!$pUser) {
                    throw new NotFoundHttpException('Utilisateur déconnecté.');
                }

                // Récupération args
                $objectId = $request->get('objectId');
                $logger->info('$objectId = ' . print_r($objectId, true));
                $objectType = $request->get('objectType');
                $logger->info('$objectType = ' . print_r($objectType, true));
                
                if ($objectType == 'debate') {
                    // Suppression élément
                    $pUFollowDDList = PUFollowDDQuery::create()
                                    ->filterByPUserId($objectId)
                                    ->filterByPDDebateId($objectId)
                                    ->find();

                    // précaution > boucle sur tous les éléments
                    foreach ($pUFollowDDList as $pUFollowDD) {
                        $pUFollowDD->delete();
                    }
                } elseif ($objectType = 'puser') {
                    // Suppression élément
                    $pUFollowUList = PUFollowUQuery::create()
                                    ->filterByPUserId($objectId)
                                    ->filterByPUserFollowerId($pUser->getId())
                                    ->find();

                    // précaution > boucle sur tous les éléments
                    foreach ($pUFollowUList as $pUFollowU) {
                        $pUFollowU->delete();
                    }
                }

                // Construction rendu
                $templating = $this->get('templating');
                $html = $templating->render(
                                    'PolitizrFrontBundle:Fragment:FollowAction.html.twig', array(
                                        'objectId' => $objectId,
                                        'objectType' => $objectType,
                                        'isFollower' => false
                                        )
                            );

                // Construction de la réponse
                $jsonResponse = array (
                    'success' => true,
                    'html' => $html
                );
            } else {
                throw $this->createNotFoundException('Not a XHR request');
            }
        } catch (NotFoundHttpException $e) {
            $logger->info('Exception = ' . print_r($e->getMessage(), true));
            $jsonResponse = array('error' => $e->getMessage());
        } catch (\Exception $e) {
            $logger->info('Exception = ' . print_r($e->getMessage(), true));
            $jsonResponse = array('error' => $e->getMessage());
        }

        // JSON formatted success/error message
        $response = new Response(json_encode($jsonResponse));
        return $response;
    }

}