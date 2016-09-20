<?php
namespace Politizr\FrontBundle\Lib\Functional;

use Politizr\Exception\InconsistentDataException;

use Politizr\Constant\ObjectTypeConstants;
use Politizr\Constant\ListingConstants;
use Politizr\Constant\UserConstants;
use Politizr\Constant\LocalizationConstants;

use Politizr\Model\PUser;

use Politizr\Model\PUserQuery;
use Politizr\Model\PTagQuery;
use Politizr\Model\PLCityQuery;

/**
 * Functional service for document management.
 * beta
 *
 * @author Lionel Bouzonville
 */
class UserService
{
    private $securityTokenStorage;
    private $securityAuthorizationChecker;
    
    private $userManager;

    private $tagService;
    private $localizationService;

    private $router;
    
    private $logger;

    /**
     *
     * @param @security.token_storage
     * @param @security.authorization_checker
     * @param @politizr.manager.user
     * @param @politizr.functional.tag
     * @param @politizr.functional.localization
     * @param @router
     * @param @logger
     */
    public function __construct(
        $securityTokenStorage,
        $securityAuthorizationChecker,
        $userManager,
        $tagService,
        $localizationService,
        $router,
        $logger
    ) {
        $this->securityTokenStorage = $securityTokenStorage;
        $this->securityAuthorizationChecker =$securityAuthorizationChecker;

        $this->userManager = $userManager;

        $this->tagService = $tagService;
        $this->localizationService = $localizationService;

        $this->router = $router;

        $this->logger = $logger;
    }


    /* ######################################################################################################## */
    /*                                              SPECIFIC LISTING                                            */
    /* ######################################################################################################## */

    /**
     * Get "homepage users" listing
     * beta
     * @param înteger $count
     * @return PropelCollection[Publication]
     */
    public function getHomepagePublicationsListing($count = ListingConstants::LISTING_HOMEPAGE_USERS_LIMIT)
    {
        $users = $this->userManager->generateHomepageUsers($count);

        return $users;
    }


    /**
     * Get filtered paginated documents
     * beta
     *
     * @param string $geoUuid
     * @param string $type
     * @param string $filterProfile
     * @param string $filterActivity
     * @param string $filterDate
     * @param integer $offset
     * @param înteger $count
     * @return PropelCollection[Publication]
     */
    public function getUsersByFilters(
        $geoUuid,
        $type,
        $filterProfile = ListingConstants::FILTER_KEYWORD_ALL_USERS,
        $filterActivity = ListingConstants::ORDER_BY_KEYWORD_MOST_ACTIVE,
        $filterDate = ListingConstants::FILTER_KEYWORD_ALL_DATE,
        $offset = 0,
        $count = ListingConstants::LISTING_CLASSIC_PAGINATION
    ) {
        $users = new \PropelCollection();

        $cityIds = [];
        if ($geoUuid && $type != LocalizationConstants::TYPE_COUNTRY) {
            $cityIds = $this->localizationService->computeCityIdsFromGeoUuid($geoUuid, $type);
        }

        $keywords = array($filterProfile, $filterDate);

        $users = PUserQuery::create()
            ->distinct()
            ->online()
            ->filterIfCities($cityIds)
            ->filterByKeywords($keywords)
            ->filterById(UserConstants::USER_ID_ADMIN, \Criteria::NOT_EQUAL)
            ->orderWithKeyword($filterActivity)
            ->paginate($offset, $count);

        return $users;
    }

    /**
     * Get paginated users by organization
     * beta
     *
     * @param array $organizationId
     * @param string $orderBy
     * @param integer $offset
     * @param înteger $count
     * @return PropelCollection PDocument
     */
    public function getUsersByOrganizationPaginated($organizationId, $filterBy = null, $orderBy = null, $offset = 0, $count = ListingConstants::LISTING_CLASSIC_PAGINATION)
    {
        $users = PUserQuery::create()
            ->distinct()
            ->online()
            ->usePUCurrentQOPUserQuery()
                ->usePUCurrentQOPQOrganizationQuery()
                    ->filterById($organizationId)
                ->endUse()
            ->endUse()
            ->filterByKeywords($filterBy)
            ->orderWithKeyword($orderBy)
            ->paginate($offset, $count);

        return $users;
    }

}
