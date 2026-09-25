<?php

namespace App\Http\Presenters\Merchant;

use App\Domain\Feeds\Queries\FeedRunErrors;
use App\Models\FeedError;
use App\Models\FeedRun;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams every stored error of one (already merchant-scoped) run as CSV,
 * read lazily in chunks through {@see FeedRunErrors::cursor()}.
 *
 * Cells are neutralised against spreadsheet formula injection (§8): a value
 * starting with = + - @ TAB or CR is prefixed with a single quote.
 */
final class FeedErrorCsv
{
    private const int CHUNK = 500;

    public function __construct(private readonly FeedRunErrors $errors) {}

    /** @var list<string> */
    private const array HEADER = ['row', 'merchant_sku', 'severity', 'code', 'field', 'message'];

    /** @var list<string> */
    private const array FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public function download(FeedRun $run): StreamedResponse
    {
        $filename = "feed-{$run->feed_source_id}-run-{$run->id}-errors.csv";

        return response()->streamDownload(function () use ($run): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            self::put($out, self::HEADER);

            $this->errors->cursor($run, self::CHUNK)->each(static function (FeedError $error) use ($out): void {
                self::put($out, [
                    $error->row_number === null ? '' : (string) $error->row_number,
                    (string) $error->item?->merchant_sku,
                    $error->severity->value,
                    $error->code,
                    (string) FeedMessages::fieldLabel($error->field),
                    FeedMessages::message($error->code, $error->message_params),
                ]);
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public static function neutralise(string $cell): string
    {
        if ($cell !== '' && in_array($cell[0], self::FORMULA_PREFIXES, true)) {
            return "'".$cell;
        }

        return $cell;
    }

    /**
     * @param  resource  $out
     * @param  list<string>  $cells
     */
    private static function put($out, array $cells): void
    {
        fputcsv($out, array_map(self::neutralise(...), $cells), ',', '"', '');
    }
}
