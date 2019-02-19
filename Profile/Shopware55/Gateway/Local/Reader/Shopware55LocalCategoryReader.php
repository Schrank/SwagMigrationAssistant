<?php declare(strict_types=1);

namespace SwagMigrationNext\Profile\Shopware55\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;

class Shopware55LocalCategoryReader extends Shopware55LocalAbstractReader
{
    public function read(): array
    {
        $fetchedCategories = $this->fetchData();

        $topMostParentIds = $this->getTopMostParentIds($fetchedCategories);
        $topMostCategories = $this->fetchCategoriesById($topMostParentIds);

        $categories = $this->mapData($fetchedCategories, [], ['category']);

        $resultSet = $this->setAllLocales($categories, $topMostCategories);

        return $this->cleanupResultSet($resultSet);
    }

    private function fetchData(): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->from('s_categories', 'category');
        $this->addTableSelection($query, 's_categories', 'category');

        $query->leftJoin('category', 's_categories_attributes', 'attributes', 'category.id = attributes.categoryID');
        $this->addTableSelection($query, 's_categories_attributes', 'attributes');

        $query->leftJoin('category', 's_media', 'asset', 'category.mediaID = asset.id');
        $this->addTableSelection($query, 's_media', 'asset');

        $query->andWhere('category.parent IS NOT NULL OR category.path IS NOT NULL');
        $query->orderBy('category.parent');
        $query->setFirstResult($this->migrationContext->getOffset());
        $query->setMaxResults($this->migrationContext->getLimit());

        return $query->execute()->fetchAll();
    }

    private function getTopMostParentIds(array $categories): array
    {
        $ids = [];
        foreach ($categories as $key => $category) {
            $parentCategoryIds = array_values(
                array_filter(explode('|', (string) $category['category.path']))
            );

            $topMostParent = end($parentCategoryIds);
            if (!in_array($topMostParent, $ids)) {
                $ids[] = $topMostParent;
            }
        }

        return $ids;
    }

    private function fetchCategoriesById(array $topMostParentIds): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('s_categories', 'category');
        $query->addSelect('category.id');

        $query->leftJoin('category', 's_core_shops', 'shop', 'category.id = shop.category_id');
        $query->leftJoin('shop', 's_core_locales', 'locale', 'locale.id = shop.locale_id');
        $query->addSelect('locale.locale');

        $query->where('category.id IN (:ids)');
        $query->setParameter('ids', $topMostParentIds, Connection::PARAM_INT_ARRAY);

        $query->orderBy('category.parent');

        return $query->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    private function setAllLocales(array $categories, array $topMostCategories): array
    {
        $resultSet = [];
        $ignoredCategories = $this->getIgnoredCategories();

        foreach ($categories as $key => $category) {
            if (empty($category['path'])) {
                $ignoredCategories[] = $category['id'];
                continue;
            }
            if (in_array($category['parent'], $ignoredCategories)) {
                $category['parent'] = null;
            }
            $parentCategoryIds = array_values(
                array_filter(explode('|', $category['path']))
            );
            $topMostParent = end($parentCategoryIds);
            $category['_locale'] = $topMostCategories[$topMostParent];
            $resultSet[] = $category;
        }

        return $resultSet;
    }

    private function getIgnoredCategories(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('category.id');
        $query->from('s_categories', 'category');
        $query->andWhere('category.path IS NULL');

        return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
    }
}