<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\Console\Command;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Sk\CustomerExtend\Api\RuleRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SetMinimumQtyCommand extends Command
{
    public const COMMAND = 'sk:customerextend:set-min-qty';

    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly RuleRepositoryInterface $ruleRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND)
            ->setDescription('Set a minimum quantity for a customer group. Applies to all Wholesale Only products.')
            ->addOption('group-id', null, InputOption::VALUE_REQUIRED, 'Customer group ID')
            ->addOption('min-qty', null, InputOption::VALUE_REQUIRED, 'Minimum quantity');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $groupId = (int)$input->getOption('group-id');
            $minQty = (float)$input->getOption('min-qty');

            if ($groupId < 0 || $minQty < 0) {
                throw new LocalizedException(__('Customer group ID and minimum quantity must be valid non-negative values.'));
            }

            $group = $this->groupRepository->getById($groupId);
            $this->ruleRepository->saveMinimumQty($groupId, $minQty);

            $output->writeln(sprintf(
                '<info>Saved: group_id=%d (%s), minimum_qty=%s</info>',
                $groupId,
                $group->getCode(),
                $minQty
            ));

            return Command::SUCCESS;
        } catch (NoSuchEntityException|LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
