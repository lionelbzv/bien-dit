<?php

namespace Politizr\Model;

use Politizr\Model\om\BaseCmsCategory;

use Politizr\FrontBundle\Lib\Tools\StaticTools;

class CmsCategory extends BaseCmsCategory
{
    /**
     *
     */
    public function __toString()
    {
        return $this->getTitle();
    }

    /**
     * Override to manage accented characters
     * @return string
     */
    protected function createRawSlug()
    {
        $toSlug =  StaticTools::transliterateString($this->getTitle());
        $slug = $this->cleanupSlugPart($toSlug);
        $slug = $this->limitSlugSize($slug);
        $slug = $this->makeSlugUnique($slug);
        return $slug;
    }
    
    /**
     *
     */
    public function preUpdate(\PropelPDO $con = null)
    {
        if ($colUpd = $this->isColumnModified(CmsCategoryPeer::TITLE)) {
            $this->slug = $this->createRawSlug();
        }
        
        return parent::preUpdate($con);
    }
}
