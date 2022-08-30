<?php
/**
 * @author Bram Gerritsen <bgerritsen@emico.nl>
 * @copyright (c) Emico B.V. 2017
 */

namespace Tweakwise\AttributeLandingTweakwise\Model;

use Tweakwise\AttributeLanding\Api\Data\FilterInterface;
use Tweakwise\AttributeLanding\Api\Data\LandingPageInterface;
use Tweakwise\AttributeLanding\Model\Filter;
use Tweakwise\AttributeLanding\Model\FilterHider\FilterHiderInterface;
use Tweakwise\AttributeLanding\Model\LandingPageContext;
use Tweakwise\AttributeLanding\Model\UrlFinder;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Filter\Item;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\FacetType\SettingsType;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;

class FilterManager
{
    /**
     * @var array
     */
    protected $activeFilters;

    /**
     * @var array
     */
    protected $activeFiltersExcludingLandingPageFilters;

    /**
     * @var Resolver
     */
    protected $layerResolver;

    /**
     * @var FilterHiderInterface
     */
    protected $filterHider;

    /**
     * @var LandingPageContext
     */
    protected $landingPageContext;

    /**
     * @var UrlFinder
     */
    protected $urlFinder;

    /**
     * FilterManager constructor.
     * @param Resolver $layerResolver
     * @param FilterHiderInterface $filterHider
     * @param LandingPageContext $landingPageContext
     * @param UrlFinder $urlFinder
     */
    public function __construct(
        Resolver $layerResolver,
        FilterHiderInterface $filterHider,
        LandingPageContext $landingPageContext,
        UrlFinder $urlFinder
    ) {
        $this->layerResolver = $layerResolver;
        $this->filterHider = $filterHider;
        $this->landingPageContext = $landingPageContext;
        $this->urlFinder = $urlFinder;
    }

    /**
     * @param Item $filterItem
     * @return string|null
     */
    public function findLandingPageUrlForFilterItem(Item $filterItem)
    {
        $layer = $this->getLayer();

        $filters = array_map(
            static function (Item $item) {
                return new Filter(
                    $item->getFilter()->getUrlKey(),
                    $item->getAttribute()->getTitle()
                );
            },
            array_merge($this->getAllActiveFilters(), [$filterItem])
        );

        $attributeLandingFilters = $this->getLandingsPageFilters();
        $filters = array_unique(
            array_merge($filters, $attributeLandingFilters),
            SORT_REGULAR
        );

        if ($url = $this->urlFinder->findUrlByFilters($filters, $layer->getCurrentCategory()->getEntityId())) {
            return $url;
        }

        return null;
    }

    /**
     * @return FilterInterface[]
     */
    public function getLandingsPageFilters()
    {
        if (!$landingsPage = $this->landingPageContext->getLandingPage()) {
            return [];
        }

        return $landingsPage->getFilters();
    }

    /**
     * @return Item[]
     */
    public function getActiveFiltersExcludingLandingPageFilters(): array
    {
        if ($this->activeFiltersExcludingLandingPageFilters === null) {
            $filters = $this->getAllActiveFilters();
            $landingPage = $this->landingPageContext->getLandingPage();
            if ($landingPage === null) {
                return $filters;
            }
            /** @var string|int $index  */
            foreach ($filters as $index => $filterItem) {
                if ($this->filterHider->shouldHideFilter(
                    $landingPage,
                    $filterItem->getFilter(),
                    $filterItem
                )) {
                    unset($filters[$index]);
                }
            }
            $this->activeFiltersExcludingLandingPageFilters = $filters;
        }
        return $this->activeFiltersExcludingLandingPageFilters;
    }

    /**
     * @return array|Item[]
     */
    public function getAllActiveFilters(): array
    {
        if ($this->activeFilters !== null) {
            return $this->activeFilters;
        }

        $filterItems = $this->getLayer()->getState()->getFilters();
        if (!\is_array($filterItems)) {
            return [];
        }
        // Do not consider category as active
        $filterItems = \array_filter($filterItems, function (Item $filter) {
            $source = $filter
                ->getFilter()
                ->getFacet()
                ->getFacetSettings()
                ->getSource();
            return $source !== SettingsType::SOURCE_CATEGORY;
        });
        $this->activeFilters = $filterItems;
        return $this->activeFilters;
    }

    /**
     * @return Layer
     */
    protected function getLayer(): Layer
    {
        return $this->layerResolver->get();
    }

    /**
     * @param LandingPageInterface $landingPage
     * @param Item $filterItem
     * @return bool
     */
    public function isFilterAvailableOnLandingPage(LandingPageInterface $landingPage, Item $filterItem): bool
    {
        foreach ($landingPage->getFilters() as $landingPageFilter) {
            if (
                $filterItem->getAttribute()->getTitle() === $landingPageFilter->getValue() &&
                $filterItem->getFilter()->getUrlKey() === $landingPageFilter->getFacet()
            ) {
                return true;
            }
        }
        return false;
    }
}
