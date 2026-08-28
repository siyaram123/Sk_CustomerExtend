<?php
declare(strict_types=1);

namespace Sk\CustomerExtend\Console\Command;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Sk\CustomerExtend\Api\RuleRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteMinimumQtyCommand extends Command
{
    public const COMMAND = 'sk:customerextend:delete-min-qty';

    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly RuleRepositoryInterface $ruleRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND)
            ->setDescription('Delete a customer group minimum quantity rule.')
            ->addOption('group-id', null, InputOption::VALUE_REQUIRED, 'Customer group ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $groupId = (int)$input->getOption('group-id');
            $group = $this->groupRepository->getById($groupId);
            $this->ruleRepository->delete($groupId);

            $output->writeln(sprintf('<info>Rule deleted for group_id=%d (%s).</info>', $groupId, $group->getCode()));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
