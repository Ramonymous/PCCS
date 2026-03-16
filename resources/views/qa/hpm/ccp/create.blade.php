<?php

use App\Models\Customer\HPM\CCP;
use App\Models\Master\FinishGood;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;

new
#[Title('Create Critical Check Point')]
class extends Component {
    use WithFileUploads, Toast;

    /* ----------
    | Form Properties
    |-------------------------------------------------------------------------- */
    public string $finish_good_id = '';
    public string $stage = 'ALL';
    
    #[Validate('nullable|image|mimes:jpg,jpeg,png,webp|max:2048')]
    public $check_point_img = null;
    
    public int $revision = 1;
    public string $description = '';
    public bool $is_active = true;

    /* ----------
    | Search Properties
    |-------------------------------------------------------------------------- */
    public string $searchFinishGood = '';

    /* ----------
    | Computed Properties
    |-------------------------------------------------------------------------- */

    /**
     * Get finish goods for select dropdown with search.
     */
    #[Computed]
    public function finishGoods()
    {
        return FinishGood::query()
            ->where('is_active', true)
            ->when($this->searchFinishGood, function($q) {
                $q->where(function($query) {
                    $query->where('part_number', 'like', "%{$this->searchFinishGood}%")
                          ->orWhere('part_name', 'like', "%{$this->searchFinishGood}%")
                          ->orWhere('model', 'like', "%{$this->searchFinishGood}%");
                });
            })
            ->orderBy('part_number')
            ->limit(50)
            ->get()
            ->map(function($fg) {
                return [
                    'id' => $fg->id,
                    'name' => $fg->part_number . ' - ' . ($fg->part_name ?? 'N/A') . 
                             ($fg->model ? " ({$fg->model})" : ''),
                ];
            })
            ->toArray();
    }

    /* ----------
    | Data for View
    |-------------------------------------------------------------------------- */

    public function with(): array
    {
        return [
            'finishGoods' => $this->finishGoods,
        ];
    }

    /* ----------
    | Actions
    |-------------------------------------------------------------------------- */

    /**
     * Save new CCP record.
     */
    public function save(): void
    {
        // Validate all fields
        $validated = $this->validate([
            'finish_good_id' => 'required|exists:finish_goods,id',
            'stage' => 'required|in:PRODUCTION CHECK,PDI CHECK,DELIVERY,ALL',
            'check_point_img' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'revision' => 'required|integer|max:50',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ], [
            'finish_good_id.required' => __('Please select Finish Good.'),
            'finish_good_id.exists' => __('Selected Finish Good is invalid.'),
            'stage.required' => __('Stage is required.'),
            'stage.in' => __('Selected stage is invalid.'),
            'check_point_img.image' => __('File must be an image'),
            'check_point_img.mimes' => __('Allowed image formats: jpg, jpeg, png, webp.'),
            'check_point_img.max' => __('Image size max 2MB.'),
            'revision.required' => __('Revision is required.'),
            'description.required' => __('Description is required.'),
            'description.max' => __('Description maximum 500 characters.'),
        ]);

        try {
            // Handle image upload
            $imgName = null;
            if ($this->check_point_img) {
                $imgName = time() . '_' . uniqid() . '.' . $this->check_point_img->getClientOriginalExtension();
                $this->check_point_img->storeAs('hpm/ccp', $imgName, 'public');
                
                Log::info('CCP Image uploaded', [
                    'filename' => $imgName,
                    'size' => $this->check_point_img->getSize()
                ]);
            }

            // Create CCP record
            $ccp = CCP::create([
                'finish_good_id' => $this->finish_good_id,
                'stage' => $this->stage,
                'check_point_img' => $imgName,
                'revision' => $this->revision,
                'description' => $this->description,
                'is_active' => $this->is_active,
            ]);

            Log::info('CCP created successfully', [
                'ccp_id' => $ccp->id,
                'finish_good_id' => $this->finish_good_id,
                'revision' => $this->revision
            ]);

            $this->success(__('CCP created successfully!'), null, 'toast-top toast-end');
            
            // Redirect to index
            $this->redirect(route('qa.hpm.ccp.index'), navigate: true);

        } catch (\Exception $e) {
            Log::error('Failed to create CCP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->error(__('Failed to create CCP: :error', ['error' => $e->getMessage()]), null, 'toast-top toast-end');
        }
    }

    /**
     * Cancel and return to index.
     */
    public function cancel(): void
    {
        $this->redirect(route('qa.hpm.ccp.index'), navigate: true);
    }
}
?>

<div>
    {{-- HEADER --}}
    <x-header 
        title="Create Critical Check Point" 
        subtitle="Add new quality checkpoint for finish goods" 
        separator 
        progress-indicator>
        <x-slot:actions>
            <x-button 
                label="Back to List" 
                icon="o-arrow-left" 
                link="{{ route('qa.hpm.ccp.index') }}" 
                class="btn-ghost" 
                responsive />
        </x-slot:actions>
    </x-header>

    {{-- FORM CARD --}}
    <x-card title="CCP Information" subtitle="Fill in the details below" shadow separator>
        <x-form wire:submit="save">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Left Column --}}
                <div class="space-y-4">
                    {{-- Finish Good Select with Search --}}
                    <x-choices-offline 
                        label="Finish Good" 
                        wire:model.live="finish_good_id"
                        :options="$finishGoods"
                        placeholder="Search by part number, name, or model..."
                        single
                        searchable
                        clearable
                        icon="o-cube"
                        hint="Start typing to search by part number, name, or model" />
                    
                    {{-- Stage Select --}}
                    <x-select 
                        label="Stage" 
                        wire:model.live="stage"
                        :options="[
                            ['id' => 'PRODUCTION CHECK', 'name' => 'Production Check (Weld)'],
                            ['id' => 'PDI CHECK', 'name' => 'PDI Check (QA)'],
                            ['id' => 'DELIVERY', 'name' => 'Delivery'],
                            ['id' => 'ALL', 'name' => 'All Stages']
                        ]"
                        icon="o-clipboard-document-check"
                        hint="Select which stage this checkpoint applies to"
                        class="select-bordered" />

                    {{-- Revision Input --}}
                    <x-input 
                        label="Revision" 
                        wire:model.live="revision" 
                        placeholder="e.g., Rev. 01, V1.0"
                        icon="o-document-text"
                        hint="Version or revision number of this checkpoint" />

                    {{-- Active Status --}}
                    <x-toggle 
                        label="Active Status" 
                        wire:model.live="is_active" 
                        hint="Set to active to use this checkpoint in production" />
                </div>

                {{-- Right Column --}}
                <div class="space-y-4">
                    {{-- Image Upload with Preview --}}
                    <div>
                        <x-file 
                            label="Check Point Image" 
                            wire:model="check_point_img" 
                            accept="image/png,image/jpeg,image/jpg,image/webp"
                            hint="Max 2MB. Formats: JPG, JPEG, PNG, WEBP">
                            <img src="https://placehold.co/600x400?text=Critical\nCheckpoint" class="h-40 rounded-lg" />
                        </x-file>
                    </div>

                    {{-- Description Textarea --}}
                    <x-textarea 
                        label="Description" 
                        wire:model.live="description" 
                        placeholder="Describe what to check at this point..."
                        rows="5"
                        hint="Detailed description of the checkpoint (max 500 characters)"
                        class="textarea-bordered" />
                </div>
            </div>

            {{-- Form Actions --}}
            <x-slot:actions>
                <x-button 
                    label="Cancel" 
                    wire:click="cancel" 
                    icon="o-x-mark"
                    class="btn-ghost" />
                <x-button 
                    label="Create CCP" 
                    type="submit" 
                    icon="o-check"
                    spinner="save"
                    class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-card>

    {{-- Validation Errors Summary --}}
    @if ($errors->any())
        <x-alert title="Validation Errors" description="Please fix the errors below:" icon="o-exclamation-triangle" class="alert-error mt-4">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif
</div>
