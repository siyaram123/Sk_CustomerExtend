<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\Model\ResourceModel\Rule;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Sk\CustomerExtend\Model\Rule as RuleModel;
use Sk\CustomerExtend\Model\ResourceModel\Rule as RuleResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(RuleModel::class, RuleResource::class);
    }
}
