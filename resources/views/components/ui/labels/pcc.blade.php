<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Part Content Card – 8 Labels (4×2)</title>
    <style>
        :root {
            --card-width: 97mm;
            --card-height: 70mm;
            --cutting-gap: 3mm;
            --card-padding: 4px;
            --border-color: #000;
            --border-width: 2px;
            --font-family: Arial, sans-serif;

            --font-base: 6.5pt;
            --font-small: 6pt;
            --font-medium: 8pt;
            --font-large: 12pt;
            --font-big-part: 12pt;
            --font-big-qty: 30pt;
            --font-big-lot: 10pt;

            --color-text: #333;
            --color-border: #999;
            --color-bg-ship: #F8BABA;
            --color-bg-lot: #f0f0f0;
            --color-bg-page: #ffffff;
        }

        @page { 
            size: A4; 
            margin: 5mm;
        }
        
        * { 
            box-sizing: border-box; 
        }
        
        body, html { 
            margin: 0; 
            padding: 0; 
            background: var(--color-bg-page); 
            font-family: var(--font-family); 
            font-size: var(--font-base); 
        }
        
        .print-container {
            display: grid;
            grid-template-columns: repeat(2, var(--card-width));
            grid-template-rows: repeat(4, var(--card-height));
            gap: var(--cutting-gap);
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            padding: calc((210mm - 2*var(--card-width) - var(--cutting-gap)) / 2) calc((297mm - 4*var(--card-height) - 3*var(--cutting-gap)) / 2);
            justify-content: center;
            align-content: center;
            page-break-after: always; /* ✅ CRITICAL: Force page break after each container */
            page-break-inside: avoid; /* ✅ Prevent breaking inside container */
        }
        
        /* ✅ TAMBAHAN: Remove page break after last container */
        .print-container:last-child {
            page-break-after: auto;
        }
        
        .card {
            border: var(--border-width) solid var(--border-color);
            padding: var(--card-padding);
            overflow: hidden;
            page-break-inside: avoid; /* ✅ CRITICAL: Prevent card from breaking across pages */
            background: white;
            display: flex;
            flex-direction: column;
        }

        .label-table { 
            width: 100%; 
            height: 100%; 
            border-collapse: collapse; 
            table-layout: fixed; 
        }
        
        .label-table td { 
            border: 1px solid var(--color-border); 
            padding: 1px 4px; 
            font-size: var(--font-base); 
            vertical-align: top; 
            line-height: 1.1; 
        }
        
        .label { 
            font-weight: normal; 
            font-size: var(--font-small); 
            color: var(--color-text); 
            text-transform: uppercase; 
            display: block; 
        }
        
        .value { 
            font-weight: bold; 
        }

        .part-name   { font-size: var(--font-medium) !important; font-weight: 900; text-align: center; vertical-align: middle; }
        .part-number { font-size: var(--font-big-part) !important; font-weight: 900; text-align: center; vertical-align: middle; }
        .ship-qty    { font-size: var(--font-big-qty) !important; font-weight: 900; text-align: center; vertical-align: middle; background: var(--color-bg-ship); color: #c00; }
        .kdm-lot-cell{ font-size: var(--font-big-lot) !important; font-weight: 900; text-align: center; vertical-align: middle; background: var(--color-bg-lot); line-height: 1.1; }

        .qr-code-cell  { text-align: center; padding: 2px; }
        .qr-code       { width: 60px; height: 60px; object-fit: contain; }
        .barcode-cell  { text-align: center; padding: 2px 0; border: none; }
        .barcode-img   { height: 30px; width: 100%; object-fit: contain; }
        .barcode-text  { text-align: center; font-size: var(--font-medium); font-family: 'Courier New', monospace; font-weight: bold; }

        @media print {
            body, html { 
                background: white; 
            }
            .print-container { 
                margin: 0; 
                background: white; 
            }
        }
    </style>
</head>
<body>

    @php        
        $labelsPerPage = 8;
        $chunks = array_chunk($labels, $labelsPerPage);
        $totalChunks = count($chunks);
    @endphp

    {{-- ✅ DEBUG: Tampilkan info di console log saat development --}}
    @if(config('app.debug'))
    <script>
        console.log('Total Labels: {{ count($labels) }}');
        console.log('Total Pages: {{ $totalChunks }}');
        console.log('Labels per Page: {{ $labelsPerPage }}');
    </script>
    @endif

    @foreach($chunks as $pageIndex => $pageLabels)
    <div class="print-container" data-page="{{ $pageIndex + 1 }}">
        @foreach($pageLabels as $labelIndex => $label)
            <div class="card" data-label="{{ ($pageIndex * $labelsPerPage) + $labelIndex + 1 }}">
                <table class="label-table">
                    <tbody>
                        <tr>
                            <td><span class="label">From</span></td>
                            <td colspan="3" rowspan="2" class="part-number">{{ $label['partNo'] ?? '' }}</td>
                            <td><span class="label">Ship</span></td>
                        </tr>
                        <tr>
                            <td class="value">{{ $label['from'] ?? '' }}</td>
                            <td rowspan="3" class="ship-qty">{{ $label['ship'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td><span class="label">To</span></td>
                            <td colspan="3" class="part-name">{{ $label['partDesc'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="value">{{ $label['to'] ?? '' }}</td>
                            <td><span class="label">Color Code</span></td>
                            <td colspan="2" class="value">{{ $label['colorCode'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td><span class="label">ASI QR</span></td>
                            <td><span class="label">P/S Code</span></td>
                            <td><span class="label">Supply Addr</span></td>
                            <td><span class="label">Next Supply Addr</span></td>
                            <td><span class="label">Order Class</span></td>
                        </tr>
                        <tr>
                            <td rowspan="5" class="qr-code-cell">
                                @if(!empty($label['mainBarcodeData']))
                                    <img class="qr-code"
                                        src="data:image/png;base64,{{ DNS2D::getBarcodePNG($label['mainBarcodeData'], 'QRCODE', 4, 4) }}"
                                        alt="QR">
                                @else
                                    Error
                                @endif
                            </td>
                            <td class="value">{{ $label['psCode'] ?? '' }}</td>
                            <td class="value">{{ $label['supplyAddress'] ?? '' }}</td>
                            <td class="value">{{ $label['nextSupplyAddress'] ?? '' }}</td>
                            <td class="value">{{ $label['orderClass'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td colspan="2"><span class="label">Prod Seq No</span></td>
                            <td colspan="2"><span class="label">KD Lot No</span></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="value">{{ $label['prodSeqNo'] ?? '' }}</td>
                            <td colspan="2" rowspan="3" class="kdm-lot-cell">{{ $label['kdLotNo'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td colspan="2"><span class="label">Inv. Category</span></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="value">{{ $label['inventoryCategory'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td><span class="label">M/S ID</span></td>
                            <td><span class="label">HNS</span></td>
                            <td colspan="3"><span class="label">Production Date & Time</span></td>
                        </tr>
                        <tr>
                            <td class="value">{{ $label['msId'] ?? '' }}</td>
                            <td class="value">{{ $label['hns'] ?? '' }}</td>
                            <td colspan="3" class="value">
                                {{ $label['formatted_date'] ?? '' }} 
                                @if(!empty($label['formatted_time']))
                                    - {{ $label['formatted_time'] }}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5" class="barcode-cell">
                                @if(!empty($label['mainBarcodeData']))
                                    <img class="barcode-img"
                                        src="data:image/png;base64,{{ DNS1D::getBarcodePNG($label['mainBarcodeData'], 'C128', 2, 33) }}"
                                    alt="barcode">
                                @else
                                    Error
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5" class="barcode-text">{{ $label['mainBarcodeData'] ?? '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
    @endforeach

</body>
</html>