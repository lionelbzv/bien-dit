<?php

namespace Politizr\Model;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Count;

use StudioEcho\Lib\StudioEchoUtils;

use Politizr\Constant\ObjectTypeConstants;
use Politizr\Constant\TagConstants;
use Politizr\Constant\LabelConstants;

use Politizr\Model\om\BasePDReaction;

/**
 * Reaction model object
 *
 * @author Lionel Bouzonville
 */
class PDReaction extends BasePDReaction implements PDocumentInterface
{
    // simple upload management
    public $uploadedFileName;

    /**
     *
     * @return string
     */
    public function __toString()
    {
        $title = $this->getTitle();

        if (!empty($title)) {
            return $this->getTitle();
        }

        return 'Pas de titre';
    }

    /**
     * @see PDocumentInterface::getType
     */
    public function getType()
    {
        return ObjectTypeConstants::TYPE_REACTION;
    }

    /**
     * @see PDocumentInterface::getDebateId
     */
    public function getDebateId()
    {
        $debate = $this->getDebate();

        if (!$debate) {
            return null;
        }
        
        return $debate->getId();
    }

    /**
     * @see PDocumentInterface::isDisplayed
     */
    public function isDisplayed()
    {
        return $this->getOnline() && $this->getPublished();
    }

    /**
     * Return constraints to be applied before publication
     *
     * @return Collection
     */
    public function getPublishConstraints()
    {
        $collectionConstraint = new Collection(array(
            'title' => array(
                new NotBlank(['message' => 'Le titre ne doit pas être vide.']),
                new Length(['max' => 100, 'maxMessage' => 'Le titre doit contenir {{ limit }} caractères maximum.']),
            ),
            'description' => array(
                new NotBlank(['message' => 'Le texte de votre document ne doit pas être vide.']),
                // new Length(['min' => 140, 'minMessage' => 'Le corps de la publication doit contenir {{ limit }} actères minimum.']),
            ),
            'themaTags' => new Count(['max' => 5, 'maxMessage' => 'Saisissez au maximum {{ limit }} thématiques libres.']),
            'localization' => new Count(['min' => 1, 'minMessage' => 'Le document doit être associé à une localisation.']),
        ));

        return $collectionConstraint;
    }

    /**
     * Override to manage accented characters
     * @return string
     */
    protected function createRawSlug()
    {
        $toSlug =  StudioEchoUtils::transliterateString($this->getTitle());
        $slug = $this->cleanupSlugPart($toSlug);
        return $slug;
    }

    /**
     * Manage publisher information
     *
     * @param \PropelPDO $con
     */
    public function preSave(\PropelPDO $con = null)
    {
        $publisher = $this->getPUser();
        if ($publisher) {
            $this->setPublishedBy($publisher->getFullName());
        } else {
            $this->setPublishedBy(LabelConstants::USER_UNKNOWN);
        }

        return parent::preSave($con);
    }

    /**
     * Compute a reaction file name
     * @todo not used for the moment
     *
     * @return string
     */
    public function computeFileName()
    {
        $fileName = 'politizr-reaction-' . StudioEchoUtils::randomString();

        return $fileName;
    }
 
    /* ######################################################################################################## */
    /*                                                  DEBATE                                                  */
    /* ######################################################################################################## */

    /**
     * @see PDocumentInterface::getDebate
     */
    public function getDebate()
    {
        return parent::getPDDebate();
    }

    /* ######################################################################################################## */
    /*                                                      TAGS                                                */
    /* ######################################################################################################## */

    /**
     * Reaction's array tags
     *
     * @return array[string]
     */
    public function getArrayTags($tagTypeId = null, $online = true)
    {
        $query = PTagQuery::create()
            ->select('Title')
            ->filterIfTypeId($tagTypeId)
            ->filterIfOnline($online)
            ->orderByTitle()
            ->setDistinct();

        return parent::getPTags($query)->toArray();
    }

    /**
     * Reaction's array tags
     *
     * @return array[id => string]
     */
    public function getIndexedArrayTags($tagTypeId = null, $online = true)
    {
        $query = PTagQuery::create()
            ->filterIfTypeId($tagTypeId)
            ->filterIfOnline($online)
            ->orderByTitle()
            ->setDistinct();

        return parent::getPTags($query)->toKeyValue('Uuid', 'Title');
    }

    /**
     * @see PDocumentInterface::getTags
     */
    public function getTags($tagTypeId = null, $online = true)
    {
        $query = PTagQuery::create()
            ->filterIfTypeId($tagTypeId)
            ->filterIfOnline($online)
            // ->orderByTitle()
            ->setDistinct();

        return parent::getPTags($query);
    }

    /**
     * @see PDocumentInterface::isWithPrivateTag
     */
    public function isWithPrivateTag()
    {
        $query = PTagQuery::create()
            ->filterByPTTagTypeId(TagConstants::TAG_TYPE_PRIVATE)
            ->setDistinct();

        $nbResults = parent::countPTags($query);
        
        if ($nbResults > 0) {
            return true;
        }

        return false;
    }

    /**
     * @see PDocumentInterface::getPLocalizations
     */
    public function getPLocalizations()
    {
        $country = parent::getPLCountry();
        $region = parent::getPLRegion();
        $department = parent::getPLDepartment();
        $city = parent::getPLCity();

        $localizations = array();

        if ($country) {
            $localizations[] = $country;
        }
        if ($region) {
            $localizations[] = $region;
        }
        if ($department) {
            $localizations[] = $department;
        }
        if ($city) {
            $localizations[] = $city;
        }

        return $localizations;
    }

    /* ######################################################################################################## */
    /*                                                  COMMENTS                                                */
    /* ######################################################################################################## */

    /**
     * @see ObjectTypeConstants::countComments
     */
    public function countComments($online = true, $paragraphNo = null, $onlyElected = null)
    {
        $query = PDRCommentQuery::create()
            ->filterIfOnline($online)
            ->filterIfOnlyElected($onlyElected)
            ->filterIfParagraphNo($paragraphNo);
        
        return parent::countPDRComments($query);
    }

    /**
     * @see ObjectTypeConstants::getComments
     */
    public function getComments($online = true, $paragraphNo = null, $orderBy = null)
    {
        $query = PDRCommentQuery::create()
            ->filterIfOnline($online)
            ->filterIfParagraphNo($paragraphNo)
            ->_if($orderBy)
                ->orderBy($orderBy[0], $orderBy[1])
            ->_else()
                ->orderBy('p_d_r_comment.created_at', 'desc')
            ->_endif();

        return parent::getPDRComments($query);
    }
    
    /* ######################################################################################################## */
    /*                                                   USERS                                                  */
    /* ######################################################################################################## */

    /**
     * @see parent::getPUser
     */
    public function getUser()
    {
        return $this->getPUser();
    }

    /**
     * @see PDocumentInterface::isOwner
     */
    public function isDebateOwner($userId)
    {
        $debate = $this->getDebate();
        if ($debate && $debate->getPUserId() == $userId) {
            return true;
        }

        return false;
    }

    /**
     * @see PDocumentInterface::isOwner
     */
    public function isOwner($userId)
    {
        if ($this->getPUserId() == $userId) {
            return true;
        }

        return false;
    }

    /* ######################################################################################################## */
    /*                                               REACTIONS                                                  */
    /* ######################################################################################################## */

    /**
     * Parent reaction of exists
     *
     * @param boolean $online
     * @param boolean $published
     * @return PDReaction
     */
    public function getParentReaction($online = null, $published = null)
    {
        $parentReaction = null;
        if ($parentReactionId = $this->getParentReactionId()) {
            $parentReaction = PDReactionQuery::create()
                ->filterIfOnline($online)
                ->filterIfPublished($published)
                ->findPk($parentReactionId);
        }

        return $parentReaction;
    }

    /**
     * Nested tree children
     *
     * @param boolean $online
     * @param boolean $published
     * @return PropelCollection[PDReaction]
     */
    public function getChildrenReactions($online = null, $published = null)
    {
        $query = PDReactionQuery::create()
            ->filterIfOnline($online)
            ->filterIfPublished($published);

        return parent::getChildren($query);
    }

    /**
     * Nested tree descendants
     *
     * @param boolean $online
     * @param boolean $published
     * @return PropelCollection[PDReaction]
     */
    public function getDescendantsReactions($online = null, $published = null)
    {
        $query = PDReactionQuery::create()
            ->filterIfOnline($online)
            ->filterIfPublished($published);

        return parent::getDescendants($query);
    }


    /**
     * Reaction's descendant count
     *
     * @param boolean $online
     * @param boolean $published
     * @return int
     */
    public function countDescendantsReactions($online = null, $published = null, $onlyElected = false)
    {
        $query = PDReactionQuery::create()
            ->filterIfOnline($online)
            ->filterIfPublished($published)
            ->orderByPublishedAt('desc');

        if ($onlyElected) {
            $query = $query->onlyElected();
        }

        return parent::countDescendants($query);
    }

    /**
     * Reaction's children count
     *
     * @param boolean $online
     * @param boolean $published
     * @return int
     */
    public function countChildrenReactions($online = null, $published = null, $onlyElected = false)
    {
        $query = PDReactionQuery::create()
            ->filterIfOnline($online)
            ->filterIfPublished($published)
            ->orderByPublishedAt('desc');

        if ($onlyElected) {
            $query = $query->onlyElected();
        }

        return parent::countChildren($query);
    }

    /**
     * @see PDReaction::countChildrenReactions
     */
    public function countReactions($online = null, $published = null, $onlyElected = false)
    {
        return $this->countChildrenReactions($online, $published, $onlyElected);
    }
}
