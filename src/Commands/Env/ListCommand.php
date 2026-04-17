<?php

declare(strict_types=1);

namespace App\Commands\Env;

use App\Command;
use App\Libs\Attributes\Route\Cli;
use App\Libs\Enums\Http\Method;
use App\Libs\Enums\Http\Status;
use Symfony\Component\Console\Input\InputInterface as iInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface as iOutput;

#[Cli(command: self::ROUTE)]
final class ListCommand extends Command
{
    public const string ROUTE = 'env:list';

    protected function configure(): void
    {
        $this
            ->setName(self::ROUTE)
            ->setDescription('List environment keys.')
            ->addOption('set', 's', InputOption::VALUE_NONE, 'Only show keys that are currently set.')
            ->addOption('expose', 'x', InputOption::VALUE_NONE, 'Expose masked values in the output.');
    }

    protected function runCommand(iInput $input, iOutput $output): int
    {
        $response = api_request(
            method: Method::GET,
            path: '/system/env',
            opts: [
                'query' => [
                    'set' => (bool) $input->getOption('set'),
                ],
            ],
        );

        if (Status::OK !== $response->status) {
            $output->writeln(r('<error>API error. {status}: {message}</error>', [
                'status' => $response->status->value,
                'message' => ag($response->body, 'error.message', 'Unknown error.'),
            ]));
            return self::FAILURE;
        }

        $mode = $input->getOption('output');
        $data = $this->sanitizeData(
            items: ag($response->body, 'data', []),
            expose: (bool) $input->getOption('expose'),
        );
        $body = $response->body;
        $body['data'] = $data;
        $file = ag($response->body, 'file');

        if ('table' === $mode) {
            if (!empty($file)) {
                $output->writeln(r('<info>Env file:</info> <comment>{file}</comment>', ['file' => $file]));
            }

            if (empty($data)) {
                $output->writeln('<comment>No environment keys matched.</comment>');
                return self::SUCCESS;
            }

            $this->displayContent(array_map(static function (array $item): array {
                $value = ag($item, 'value', ag($item, 'config_value'));

                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }

                return [
                    'key' => ag($item, 'key'),
                    'value' => $value,
                ];
            }, $body['data']), $output, $mode);
            return self::SUCCESS;
        }

        $this->displayContent($body, $output, $mode);

        return self::SUCCESS;
    }

    private function sanitizeData(array $items, bool $expose): array
    {
        if ($expose) {
            return $items;
        }

        return array_map(static function (array $item): array {
            if (true !== (bool) ag($item, 'mask', false)) {
                return $item;
            }

            if (true === ag_exists($item, 'value') && null !== $item['value']) {
                $item['value'] = '*HIDDEN*';
            }

            if (true === ag_exists($item, 'config_value') && null !== $item['config_value']) {
                $item['config_value'] = '*HIDDEN*';
            }

            return $item;
        }, $items);
    }
}
