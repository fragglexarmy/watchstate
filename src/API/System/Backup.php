<?php

declare(strict_types=1);

namespace App\API\System;

use App\Libs\Attributes\Route\Get;
use App\Libs\Attributes\Route\Route;
use App\Libs\Config;
use App\Libs\Enums\Http\Method;
use App\Libs\Enums\Http\Status;
use App\Libs\Stream;
use CallbackFilterIterator;
use DirectoryIterator;
use finfo;
use Psr\Http\Message\ResponseInterface as iResponse;
use Psr\Http\Message\ServerRequestInterface as iRequest;

final class Backup
{
    public const string URL = '%{api.prefix}/system/backup';

    private const array EXTENSIONS = ['json', 'zip'];

    #[Get(self::URL . '[/]', name: 'system.backup')]
    public function list(): iResponse
    {
        $list = [];
        $files = new CallbackFilterIterator(
            new DirectoryIterator(fix_path(Config::get('path') . '/backup')),
            static function (DirectoryIterator $file): bool {
                if ($file->isDot() || $file->isDir() || $file->isLink() || false === $file->isFile()) {
                    return false;
                }

                return in_array(strtolower((string) get_extension($file->getBasename())), self::EXTENSIONS, true);
            },
        );

        foreach ($files as $file) {
            $isAuto = 1 === preg_match('/^(\w+\.)?\w+\.\d{8}\.json(\.zip)?$/i', $file->getBasename());

            $builder = [
                'filename' => $file->getBasename(),
                'type' => $isAuto ? 'automatic' : 'manual',
                'size' => $file->getSize(),
                'date' => $file->getMTime(),
            ];

            $list[] = $builder;
        }

        $sorter = array_column($list, 'date');
        array_multisort($sorter, SORT_DESC, $list);

        foreach ($list as &$item) {
            $item['date'] = make_date(ag($item, 'date'));
        }

        return api_response(Status::OK, $list);
    }

    #[Route(['GET', 'DELETE'], self::URL . '/{filename}[/]', name: 'system.backup.view')]
    public function read(iRequest $request, string $filename): iResponse
    {
        $path = realpath(fix_path(Config::get('path') . '/backup'));

        if (false === ($filePath = realpath($path . '/' . $filename))) {
            return api_error('File not found.', Status::NOT_FOUND);
        }

        if (!in_array(strtolower((string) get_extension($filename)), self::EXTENSIONS, true)) {
            return api_error('Invalid file type.', Status::BAD_REQUEST);
        }

        if (false === str_starts_with($filePath, $path)) {
            return api_error('Invalid file path.', Status::BAD_REQUEST);
        }

        if (Method::DELETE === Method::from($request->getMethod())) {
            unlink($filePath);
            return api_response(Status::OK);
        }

        $mime = new finfo(FILEINFO_MIME_TYPE)->file($filePath);

        return api_response(Status::OK, Stream::make($filePath, 'r'), headers: [
            'Content-Type' => false === $mime ? 'application/octet-stream' : $mime,
        ]);
    }
}
