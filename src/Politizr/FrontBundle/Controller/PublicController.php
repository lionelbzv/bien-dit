<?php

namespace Politizr\FrontBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Politizr\Constant\ListingConstants;
use Politizr\Constant\GlobalConstants;
use Politizr\Constant\LocalizationConstants;

use Politizr\Model\PDDirect;

use Politizr\Model\PDDebateQuery;
use Politizr\Model\PDReactionQuery;
use Politizr\Model\PUserQuery;
use Politizr\Model\PTagQuery;
use Politizr\Model\PQOrganizationQuery;
use Politizr\Model\PMCguQuery;
use Politizr\Model\PMCharteQuery;

use Politizr\FrontBundle\Form\Type\PDDirectType;

use Eko\FeedBundle\Field\Item\MediaItemField;

/**
 * Public controller
 *
 * @author  Lionel Bouzonville
 */
class PublicController extends Controller
{
    /**
     * Homepage
     */
    public function homepageAction()
    {
        $logger = $this->get('logger');
        $logger->info('*** homepageAction');

        // redirect if connected
        if ($profileSuffix = $this->get('politizr.tools.global')->computeProfileSuffix()) {
            return $this->redirect($this->generateUrl(sprintf('Homepage%s', $profileSuffix)));
        }

        return $this->render('PolitizrFrontBundle:Public:homepage.html.twig', array(
            'homepage' => true,
        ));
    }

    /**
     * Qui sommes nous
     * code beta
     */
    public function whoWeAreAction()
    {
        $logger = $this->get('logger');
        $logger->info('*** whoWeAreAction');

        return $this->render('PolitizrFrontBundle:Public:whoWeAre.html.twig', array(
        ));
    }

    /**
     * Notre concept
     * code beta
     */
    public function conceptAction()
    {
        $logger = $this->get('logger');
        $logger->info('*** conceptAction');

        return $this->render('PolitizrFrontBundle:Public:concept.html.twig', array(
        ));
    }

    /**
     * CGU
     */
    public function cguAction()
    {
        $logger = $this->get('logger');
        $logger->info('*** cguAction');

        $legal = PMCguQuery::create()->filterByOnline(true)->orderByCreatedAt('desc')->findOne();

        return $this->render('PolitizrFrontBundle:Public:cgu.html.twig', array(
            'legal' => $legal,
        ));
    }

    /**
     * Charte publique
     */
    public function charteAction()
    {
        $logger = $this->get('logger');
        $logger->info('*** charteAction');

        $charte = PMCharteQuery::create()->findPk(GlobalConstants::GLOBAL_CHARTE_ID);

        return $this->render('PolitizrFrontBundle:Public:charte.html.twig', array(
            'charte' => $charte,
        ));
    }

    /**
     * RSS feed
     */
    public function rssFeedAction()
    {
        $publications = $this->get('politizr.functional.document')->getPublicationsByFilters(
            null,
            null,
            null,
            null,
            ListingConstants::FILTER_KEYWORD_DEBATES_AND_REACTIONS,
            ListingConstants::FILTER_KEYWORD_ALL_USERS,
            ListingConstants::ORDER_BY_KEYWORD_LAST,
            ListingConstants::FILTER_KEYWORD_ALL_DATE,
            0,
            ListingConstants::LISTING_RSS
        );

        $feed = $this->get('eko_feed.feed.manager')->get('debates');
        $feed->addFromArray((array) $publications);
        $feed->addItemField(new MediaItemField('getFeedMediaItem'));

        return new Response($feed->render('rss')); // or 'atom'
    }

    /**
     * Generate robots.txt
     */
    public function robotsTxtAction()
    {
        // Render robots.txt
        $response = new Response();
        $response->headers->set('Content-Type', 'text/plain');
        $response->sendHeaders();
        
        return $this->render(
            'PolitizrFrontBundle:Navigation:robots.txt.twig',
            array(),
            $response
        );
    }

    /**
     * Generate sitemap
     */
    public function sitemapXmlAction()
    {
        $urls = [];

        // homepage
        $url = $this->generateUrl('Homepage');
        $urls[] = $this->generateUrlItem($url, 'weekly', '0.3');

        // top
        $url = $this->generateUrl('ListingByRecommend');
        $urls[] = $this->generateUrlItem($url, 'weekly', '0.8');

        // listing thématiques
        $contents = PTagQuery::create()
            ->filterByOnline(true)
            ->joinPDDTaggedT(null, 'left join')
            ->distinct()
            ->orderById('desc')
            ->find();

        foreach ($contents as $content) {
            $url = $this->generateUrl('ListingByTag', array(
                'slug' => $content->getSlug(),
                ));
            $urls[] = $this->generateUrlItem($url, 'weekly', '0.5');
        }

        // listing par organisations
        $contents = PQOrganizationQuery::create()
            ->filterByOnline(true)
            ->orderByRank()
            ->find();

        foreach ($contents as $content) {
            $url = $this->generateUrl('ListingByOrganization', array(
                'slug' => $content->getSlug(),
                ));
            $urls[] = $this->generateUrlItem($url, 'weekly', '0.5');
        }

        // pages debats
        $contents = PDDebateQuery::create()
            ->filterByOnline(true)
            ->filterByPublished(true)
            ->orderByPublishedAt('desc')
            ->find();

        foreach ($contents as $content) {
            $url = $this->generateUrl('DebateDetail', array(
                'slug' => $content->getSlug(),
                ));
            $urls[] = $this->generateUrlItem($url, 'weekly', '0.7');
        }

        // pages réactions
        $contents = PDReactionQuery::create()
            ->filterByOnline(true)
            ->filterByPublished(true)
            ->orderByPublishedAt('desc')
            ->find();

        foreach ($contents as $content) {
            $url = $this->generateUrl('ReactionDetail', array(
                'slug' => $content->getSlug(),
                ));
            $urls[] = $this->generateUrlItem($url, 'weekly', '0.7');
        }

        // pages users
        $contents = PUserQuery::create()
            ->filterByOnline(true)
            ->orderByCreatedAt('desc')
            ->find();

        foreach ($contents as $content) {
            $url = $this->generateUrl('UserDetail', array(
                'slug' => $content->getSlug(),
                ));
            $urls[] = $this->generateUrlItem($url, 'weekly', '0.3');
        }

        // landing pages
        $keywords = [ 'civic-tech', 'elu-local', 'dialogue-citoyen', 'democratie-locale', 'democratie-participative', 'reseau-social-politique', 'primaires-presidentielle-2017', 'charlotte-marchandise-franquet'];
        foreach ($keywords as $keyword) {
            $url = $this->generateUrl('LandingPage', array(
                'theme' => $keyword
                ));
            $urls[] = $this->generateUrlItem($url, 'weekly', '0.3');
        }

        // Render XML Sitemap
        $response = new Response();
        $response->headers->set('Content-Type', 'xml');
        
        return $this->render(
            'PolitizrFrontBundle:Navigation:sitemap.xml.twig',
            array(
                'urls' => $urls
            ),
            $response
        );
    }

    /**
     * Generate the url item
     * @return array
     */
    private function generateUrlItem($url, $changefreq = 'monthly', $priority = '0.3', $subdomain = false)
    {
        if ($subdomain) {
            $loc = $this->getRequest()->getScheme() . ':' . $url;
        } else {
            $loc = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHost() . $url;
        }
        return array(
            'loc'        => $loc,
            'changefreq' => $changefreq,
            'priority'   => $priority
        );
    }
}
