<?php

namespace App\Jobs;

use App\Models\Customer\HPM\Pcc;
use App\Models\User;
use App\Notifications\PrintJobComplete;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class PrintLabelsPCC implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;

    private const BARCODE_MAX_LENGTH = 250;
    private const MIN_FILE_SIZE_PER_LABEL = 100;
    private const LABELS_PER_PAGE = 8;
    private const STORAGE_DIRECTORY = 'print/labels/pccs';
    
    // Path spesifik sesuai request
    private const CUSTOM_CHROME_PATH = '/var/www/.cache/puppeteer/chrome-headless-shell/linux-138.0.7204.168/chrome-headless-shell-linux64/chrome-headless-shell';

    public function __construct(
        protected array $selectedIds,
        protected User $user
    ) {}

    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        try {
            $validIds = $this->validateAndFilterIds();
            
            if (empty($validIds)) {
                $this->notifyAndAbort('Tidak ada data yang valid untuk dicetak.');
                return;
            }

            // Log processing awal dihapus agar lebih simple

            $dataToPrint = $this->fetchData($validIds);

            if ($dataToPrint->isEmpty()) {
                $this->notifyAndAbort('Tidak ada data yang ditemukan sesuai kriteria yang dipilih.');
                return;
            }

            [$labels, $skippedCount] = $this->mapLabels($dataToPrint);

            if (empty($labels)) {
                $this->notifyAndAbort('Tidak ada label valid yang bisa diproses.');
                return;
            }

            // Log mapping success dihapus

            $storagePath = $this->generatePdf($labels);
            $this->verifyPdfFile($storagePath, count($labels));

            $downloadUrl = Storage::disk('public')->url($storagePath);
            $this->user->notify(new PrintJobComplete($downloadUrl, 'File PDF Anda telah siap. Klik untuk mengunduh.'));

            // Log success disederhanakan

        } catch (Throwable $e) {
            $this->handleError($e);
            throw $e;
        }
    }

    private function validateAndFilterIds(): array
    {
        $validIds = array_filter($this->selectedIds, fn($item) => is_string($item) && !empty(trim($item)));
        
        // Log warning validasi dihapus/disederhanakan, hanya return
        return array_values($validIds);
    }

    private function fetchData(array $validIds)
    {
        // Log debug query dihapus
        return Pcc::with(['schedule:id,slip_number,schedule_date,adjusted_date,schedule_time,adjusted_time'])
            ->whereIn('id', $validIds)
            ->select([
                'id', 'from', 'to', 'part_no', 'part_name', 'color_code',
                'supply_address', 'next_supply_address', 'ps_code', 'order_class',
                'prod_seq_no', 'kd_lot_no', 'ms_id', 'inventory_category',
                'ship', 'hns', 'slip_barcode', 'slip_no', 'date', 'time'
            ])
            ->get();
    }

    private function mapLabels($dataToPrint): array
    {
        $labels = [];
        $skippedCount = 0;

        foreach ($dataToPrint as $item) {
            try {
                $barcodeData = $this->sanitizeBarcode($item->slip_barcode ?? '');

                if (empty($barcodeData)) {
                    $skippedCount++;
                    continue;
                }

                $labels[] = $this->buildLabelData($item, $barcodeData);

            } catch (\Exception $e) {
                // Log error per item tetap ada tapi minimal
                $skippedCount++;
            }
        }

        return [$labels, $skippedCount];
    }

    private function sanitizeBarcode(string $barcode): string
    {
        $barcode = trim($barcode);
        $barcode = preg_replace('/[\x00-\x1F\x7F]/', '', $barcode);

        if (strlen($barcode) > self::BARCODE_MAX_LENGTH) {
            $barcode = substr($barcode, 0, self::BARCODE_MAX_LENGTH);
        }

        return $barcode;
    }

    private function buildLabelData($item, string $barcodeData): array
    {
        return [
            'from' => $item->from ?? '',
            'to' => $item->to ?? '',
            'partNo' => $item->part_no ?? '',
            'partDesc' => $item->part_name ?? '',
            'colorCode' => $item->color_code ?? '',
            'supplyAddress' => $item->supply_address ?? '',
            'nextSupplyAddress' => $item->next_supply_address ?? '',
            'psCode' => $item->ps_code ?? '',
            'orderClass' => $item->order_class ?? '',
            'prodSeqNo' => $item->prod_seq_no ?? '',
            'kdLotNo' => $item->kd_lot_no ?? '',
            'msId' => $item->ms_id ?? '',
            'inventoryCategory' => $item->inventory_category ?? '',
            'ship' => $item->ship ?? 0,
            'hns' => $item->hns ?? '',
            'formatted_date' => $item->effective_date ?? '',
            'formatted_time' => $item->effective_time ?? '',
            'mainBarcodeData' => $barcodeData,
        ];
    }

    private function generatePdf(array $labels): string
    {
        $filename = "labels-{$this->user->id}-" . now()->timestamp . '.pdf';
        $storagePath = self::STORAGE_DIRECTORY . "/{$filename}";

        Storage::disk('public')->makeDirectory(self::STORAGE_DIRECTORY, 0755, true);

        // 1. Cek .env
        $chromePath = env('BROWSERSHOT_CHROME_PATH');
        
        // 2. Cek Path Spesifik (Hardcoded/Cache Puppeteer)
        if (!$chromePath) {
            if (file_exists(self::CUSTOM_CHROME_PATH) && is_executable(self::CUSTOM_CHROME_PATH)) {
                $chromePath = self::CUSTOM_CHROME_PATH;
            }
        }

        // 3. Fallback ke System Chrome umum (jika custom path tidak ketemu)
        if (!$chromePath) {
            $possible = [
                '/usr/bin/google-chrome',
                '/usr/bin/chromium',
                '/usr/bin/chromium-browser',
                '/usr/bin/chrome',
            ];
            foreach ($possible as $p) {
                if (is_file($p) && is_executable($p)) {
                    $chromePath = $p;
                    break;
                }
            }
        }

        if (!$chromePath || !is_executable($chromePath)) {
            throw new \Exception("Chrome binary not found. Please check server configuration.");
        }

        Pdf::view('components.ui.labels.pcc', ['labels' => $labels])
            ->withBrowsershot(function ($browsershot) use ($chromePath) {
                $browsershot
                    ->noSandbox()
                    ->setOption('args', [
                        '--no-sandbox',
                        '--disable-setuid-sandbox',
                        '--disable-web-security',
                        '--disable-dev-shm-usage',
                        '--disable-gpu',
                        '--disable-software-rasterizer', // Fix for memlock issues
                        '--disable-breakpad',            // Disable crash reporter
                        '--mute-audio'                   // Prevent audio sub-system memlock
                    ])
                    ->paperSize(210, 297)
                    ->margins(0, 0, 0, 0, 'mm')
                    ->showBackground()
                    ->waitUntilNetworkIdle()
                    ->timeout(300);

                if ($chromePath) {
                    try {
                        $browsershot->setChromePath($chromePath);
                    } catch (\Throwable $e) {
                        // Silent fail or minimal log
                    }
                }
            })
            ->save(Storage::disk('public')->path($storagePath));

        return $storagePath;
    }

    private function verifyPdfFile(string $storagePath, int $labelCount): void
    {
        if (!Storage::disk('public')->exists($storagePath)) {
            throw new \Exception("PDF creation failed");
        }

        $fileSize = Storage::disk('public')->size($storagePath);
        if ($fileSize === 0) {
            throw new \Exception('PDF file is 0 bytes');
        }
    }

    private function notifyAndAbort(string $message): void
    {
        // Log disederhanakan
        Log::warning("PrintLabelsPCC Aborted: {$message}");
        $this->user->notify(new PrintJobComplete(null, $message));
    }

    private function handleError(Throwable $e): void
    {
        $msg = $e->getMessage();
        $isChromeError = str_contains($msg, 'Browser') || str_contains($msg, 'Chrome');
        
        Log::error('PrintLabelsPCC Failed', [
            'user' => $this->user->id,
            'error' => $msg,
            'line' => $e->getLine()
        ]);

        $userMessage = $isChromeError 
            ? 'Server Error: Konfigurasi PDF belum sesuai.'
            : 'Terjadi kesalahan saat membuat PDF.';
        
        $this->user->notify(new PrintJobComplete(null, $userMessage));
    }
}