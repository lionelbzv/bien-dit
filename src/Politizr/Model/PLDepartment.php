<?php

namespace Politizr\Model;

use Politizr\FrontBundle\Lib\Tag;

use Politizr\Constant\LocalizationConstants;
use Politizr\Constant\ObjectTypeConstants;
use Politizr\Constant\TagConstants;

use Politizr\FrontBundle\Lib\PLocalization;

use Politizr\Model\om\BasePLDepartment;

class PLDepartment extends BasePLDepartment implements Tag, PLocalization
{
    /**
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getTitle();
    }

    /**
     *
     * @return string
     */
    public function getLocType()
    {
        return LocalizationConstants::TYPE_DEPARTMENT;
    }

    /**
     *
     * @return string
     */
    public function getType()
    {
        return ObjectTypeConstants::TYPE_LOCALIZATION_DEPARTMENT;
    }

    /**
     *
     * @return int
     */
    public function getTagType()
    {
        return TagConstants::TAG_TYPE_GEO;
    }

    /**
     *
     * @return int
     */
    public function countUsers()
    {
        return null;
    }

    /**
     * Sum of count debates & reactions
     *
     * @param boolean $onlyPublished
     * @return integer
     * @return int
     */
    public function countDocuments($onlyPublished = true)
    {
        $queryDebate = PDDebateQuery::create()
            ->_if($onlyPublished)
                ->online()
            ->_endif()
        ;

        $queryReaction = PDReactionQuery::create()
            ->_if($onlyPublished)
                ->online()
            ->_endif()
        ;

        return parent::countPDDebates($queryDebate) + parent::countPDReactions($queryReaction);
    }
}
