<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\Model;

use Magento\Framework\Model\AbstractModel;

class Rule extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(\Sk\CustomerExtend\Model\ResourceModel\Rule::class);
    }
}
