# QR Scanner Component Usage Examples

## Basic Usage

```blade
<livewire:ui.qr-scanner 
    id="my-scanner"
    label="Scan Labels"
    placeholder="Scan or type barcode..."
    :show-manual-input="true"
/>
```

## Parent Component Example

```php
<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    #[On('barcode-scanned')]
    public function handleScan(string $barcode): void
    {
        // Your custom logic here
        $this->success("Scanned: {$barcode}");
        
        // Dispatch feedback to scanner
        $this->dispatch('scan-feedback', type: 'success');
    }
}; ?>

<div>
    <livewire:ui.qr-scanner 
        id="example-scanner"
        label="My Scanner"
        :auto-start="false"
        facing-mode="environment"
    />
</div>
```

## Available Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `id` | string | 'default' | Unique identifier (required if multiple scanners) |
| `label` | string | 'QR/Barcode Scanner' | Card title |
| `placeholder` | string | 'Scan atau ketik barcode...' | Manual input placeholder |
| `show-manual-input` | bool | true | Show/hide manual input field |
| `auto-start` | bool | false | Auto-start scanning on mount |
| `facing-mode` | string | 'environment' | Camera mode: 'environment' (back) or 'user' (front) |

## Events

### Listening to Scans

Use the `#[On('barcode-scanned')]` attribute:

```php
#[On('barcode-scanned')]
public function processScan(string $barcode): void
{
    // Your logic
}
```

### Providing Feedback

Dispatch feedback events to control scanner behavior:

```php
// Success feedback (green flash + beep)
$this->dispatch('scan-feedback', type: 'success');

// Error feedback (red flash + buzz)
$this->dispatch('scan-feedback', type: 'error');

// Warning feedback (orange flash + tone)
$this->dispatch('scan-feedback', type: 'warning');
```

## Multiple Scanners

Use unique IDs for multiple scanners on the same page:

```blade
<livewire:ui.qr-scanner id="scanner-1" label="Station 1" />
<livewire:ui.qr-scanner id="scanner-2" label="Station 2" />
```

## Advanced Example: QA Check with Validation

```php
<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Mary\Traits\Toast;
use App\Models\Product;

new class extends Component {
    use Toast;

    public string $currentBatch = '';
    public int $scannedCount = 0;

    #[On('barcode-scanned')]
    public function validateProduct(string $barcode): void
    {
        $product = Product::where('barcode', $barcode)->first();

        if (!$product) {
            $this->error('Product not found!');
            $this->dispatch('scan-feedback', type: 'error');
            return;
        }

        if ($product->batch !== $this->currentBatch) {
            $this->warning('Wrong batch!');
            $this->dispatch('scan-feedback', type: 'warning');
            return;
        }

        // Pass validation
        $this->scannedCount++;
        $this->success("✓ {$product->name}");
        $this->dispatch('scan-feedback', type: 'success');
    }
}; ?>

<div>
    <x-card title="QA Station">
        <x-input label="Batch Number" wire:model="currentBatch" />
        <div class="my-4">Scanned: {{ $scannedCount }}</div>
    </x-card>

    <livewire:ui.qr-scanner 
        id="qa-scanner"
        label="QA Scanner"
        auto-start="true"
    />
</div>
```

## Auto-Start Scanner

For dedicated scanning stations:

```blade
<livewire:ui.qr-scanner 
    id="kiosk-scanner"
    label="Kiosk Scanner"
    :auto-start="true"
    :show-manual-input="false"
/>
```
