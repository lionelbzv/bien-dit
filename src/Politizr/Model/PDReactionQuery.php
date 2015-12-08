<?php

namespace Politizr\Model;

use Politizr\Model\om\BasePDReactionQuery;

use Politizr\Constant\ListingConstants;

/**
 * Reaction query
 *
 * @author Lionel Bouzonville
 */
class PDReactionQuery extends BasePDReactionQuery
{
    /* ######################################################################################################## */
    /*                                                  RAW SQL                                                 */
    /* ######################################################################################################## */
    
    /**
     * Reactions' suggestion for user.
     *
     * @see app/sql/suggestions.sql
     *
     * @param  integer     $userId
     * @param  integer     $offset
     * @param  integer     $limit
     * @return string
     */
    private function getSuggestionsSql($userId, $offset, $limit = 10)
    {
        // Requête SQL
        $sql = "
SELECT DISTINCT
    id,
    uuid,
    p_user_id,
    p_d_debate_id,
    parent_reaction_id,
    title,
    file_name,
    copyright,
    description,
    note_pos,
    note_neg,
    nb_views,
    published,
    published_at,
    published_by,
    favorite,
    online,
    moderated,
    moderated_partial,
    moderated_at,
    created_at,
    updated_at,
    slug,
    tree_left,
    tree_right,
    tree_level
FROM (
    ( SELECT DISTINCT p_d_reaction.*, 0 as nb_users, 1 as unionsorting
        FROM p_d_reaction
            LEFT JOIN p_d_r_tagged_t
                ON p_d_reaction.id = p_d_r_tagged_t.p_d_reaction_id
        WHERE
            p_d_r_tagged_t.p_tag_id IN (
                SELECT p_tag.id
                FROM p_tag
                    LEFT JOIN p_u_tagged_t
                        ON p_tag.id = p_u_tagged_t.p_tag_id
                WHERE
                    p_tag.online = true
                    AND p_u_tagged_t.p_user_id = ".$userId."
            )
        AND p_d_reaction.online = 1
        AND p_d_reaction.published = 1
        AND p_d_reaction.id NOT IN (SELECT p_d_reaction_id FROM p_u_follow_d_d WHERE p_user_id = ".$userId.")
        AND p_d_reaction.p_user_id <> ".$userId."
    )
) unionsorting

LIMIT ".$offset.", ".$limit."
";

        return $sql;
    }

    /**
     * PDDebate objects hydratation from raw sql
     *
     * @param string $sql
     * @return PropelCollection
     */
    private function hydrateFromSql($sql)
    {
        $timeline = array();

        if ($sql) {
            // Exécution de la requête brute
            $con = \Propel::getConnection('default', \Propel::CONNECTION_READ);
            $stmt = $con->prepare($sql);
            $stmt->execute();

            $result = $stmt->fetchAll();

            $collection = new \PropelCollection();
            foreach ($result as $row) {
                $debate = new PDDebate();
                $debate->hydrate($row);

                $collection->append($debate);
            }
        }

        return $collection;
    }

    /**
     * Find reactions by user's suggestion
     *
     * @param integer $userId
     * @param integer $offset
     * @param integer $limit
     * @return PropelCollection[PDDebate]
     */
    public function findBySuggestion($userId, $offset = 0, $limit = 10)
    {
        $sql = $this->getSuggestionsSql($userId, $offset, $limit);
        $reactions = $this->hydrateFromSql($sql);

        return $reactions;
    }

    /* ######################################################################################################## */
    /*                                               AGGREGATION                                                */
    /* ######################################################################################################## */

    /**
     *
     */
    public function online()
    {
        return $this->filterByOnline(true)->filterByPublished(true);
    }

    /* ######################################################################################################## */
    /*                                         CUSTOM FILTERS / ORDERS                                          */
    /* ######################################################################################################## */
    
    /**
     * Order by keyword
     *
     * @param string $keyword
     * @return PDReactionQuery
     */
    public function orderWithKeyword($keyword = ListingConstants::ORDER_BY_KEYWORD_LAST)
    {
        return $this
            ->_if(ListingConstants::ORDER_BY_KEYWORD_BEST_NOTE === $keyword)
                ->orderByNote()
            ->_elseif(ListingConstants::ORDER_BY_KEYWORD_LAST === $keyword)
                ->orderByLast()
            ->_elseif(ListingConstants::ORDER_BY_KEYWORD_MOST_REACTIONS === $keyword)
                ->orderByMostReactions()
            ->_elseif(ListingConstants::ORDER_BY_KEYWORD_MOST_COMMENTS === $keyword)
                ->orderByMostComments()
            ->_elseif(ListingConstants::ORDER_BY_KEYWORD_MOST_VIEWS === $keyword)
                ->orderByMostViews()
            ->_endif();
    }

    /**
     * Order by note pos
     *
     * @return PDReactionQuery
     */
    public function orderByNote()
    {
        return $this->orderByNotePos('desc')->orderByNoteNeg('asc');
    }

    /**
     * Order by number of reactions
     *
     * @return PDReactionQuery
     */
    public function orderByMostReactions()
    {
        return $this
            ->withColumn('(p_d_reaction.tree_right - p_d_reaction.tree_left)', 'NbChildrenReactions')
            ->where('(p_d_reaction.tree_right - p_d_reaction.tree_left) > 1')
            ->orderBy('NbChildrenReactions', 'desc');
    }

    /**
     * Order by number of comments
     *
     * @return PDReactionQuery
     */
    public function orderByMostComments()
    {
        return $this
            ->withColumn('COUNT(p_d_r_comment.Id)', 'NbComments')
            ->join('PDRComment', \Criteria::LEFT_JOIN)
            ->where('PDRComment.online = true')
            ->groupBy('Id')
            ->orderBy('NbComments', 'desc');
    }

    /**
     * Order by number of views
     *
     * @return PDReactionQuery
     */
    public function orderByMostViews()
    {
        return $this
            ->orderByNbViews('desc');
    }

    /**
     * Order by last published
     *
     * @return PDReactionQuery
     */
    public function orderByLast()
    {
        return $this->orderByPublishedAt('desc');
    }

    /**
     * Filter by keyword
     *
     * @param array[string] $keywords
     * @return PDReactionQuery
     */
    public function filterByKeywords($keywords = null)
    {
        return $this
            ->_if($keywords && (in_array(ListingConstants::FILTER_KEYWORD_LAST_DAY, $keywords)))
                ->filterByLastDay()
            ->_endif()
            ->_if($keywords && (in_array(ListingConstants::FILTER_KEYWORD_LAST_WEEK, $keywords)))
                ->filterByLastWeek()
            ->_endif()
            ->_if($keywords && (in_array(ListingConstants::FILTER_KEYWORD_LAST_MONTH, $keywords)))
                ->filterByLastMonth()
            ->_endif()
            ->_if($keywords && in_array(ListingConstants::FILTER_KEYWORD_QUALIFIED, $keywords))
                ->filterByQualified()
            ->_endif()
            ->_if($keywords && in_array(ListingConstants::FILTER_KEYWORD_CITIZEN, $keywords))
                ->filterByCitizen()
            ->_endif();
    }

    /**
     * Filter by published during last 24h
     *
     * @return PDReactionQuery
     */
    public function filterByLastDay()
    {
        // Dates début / fin
        $now = new \DateTime();
        $fromAt = new \DateTime();
        $fromAt->modify('-1 day');

        return $this->filterByPublishedAt(array('min' => $fromAt));
    }

    /**
     * Filter by published during last week
     *
     * @return PDReactionQuery
     */
    public function filterByLastWeek()
    {
        // Dates début / fin
        $now = new \DateTime();
        $fromAt = new \DateTime();
        $fromAt->modify('-1 week');

        return $this->filterByPublishedAt(array('min' => $fromAt));
    }

    /**
     * Filter by published during last month
     *
     * @return PDReactionQuery
     */
    public function filterByLastMonth()
    {
        // Dates début / fin
        $now = new \DateTime();
        $fromAt = new \DateTime();
        $fromAt->modify('-1 month');

        return $this->filterByPublishedAt(array('min' => $fromAt));
    }

    /**
     * Filter by qualified
     *
     * @return PDReactionQuery
     */
    public function filterByQualified()
    {
        return $this
            ->usePUserQuery()
                ->filterByQualified(true)
            ->endUse();
    }

    /**
     * Filter by not qualified
     *
     * @return PDReactionQuery
     */
    public function filterByCitizen()
    {
        return $this
            ->usePUserQuery()
                ->filterByQualified(false)
            ->endUse();
    }

    /**
     * Filter by array of tags id
     *
     * @param array[int]
     * @return PDReactionQuery
     */
    public function filterByTags($tagIds)
    {
        return $this
            ->usePDRTaggedTQuery()
                ->filterByPTagId($tagIds)
            ->endUse();
    }

    /**
     * Filter by geolocalization
     *
     * @param Geocoded $geocoded
     * @return PDReactionQuery
     */
    public function filterByGeolocalization(Geocoded $geocoded)
    {
    }
    
    /* ######################################################################################################## */
    /*                                              FILTERBY IF                                                 */
    /* ######################################################################################################## */

    /**
     *
     * @param boolean $online
     */
    public function filterIfOnline($online = null)
    {
        return $this
            ->_if(null !== $online)
                ->filterByOnline($online)
            ->_endif();
    }

    /**
     *
     * @param boolean $published
     */
    public function filterIfPublished($published = null)
    {
        return $this
            ->_if(null !== $published)
                ->filterByPublished($published)
            ->_endif();
    }

    /**
     *
     * @param boolean $treeLevel
     */
    public function filterIfTreeLevel($treeLevel = null)
    {
        return $this
            ->_if(null !== $treeLevel)
                ->filterByTreeLevel($treeLevel)
            ->_endif();
    }
}
