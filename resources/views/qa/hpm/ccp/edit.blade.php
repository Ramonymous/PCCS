<?php

use App\Models\Customer\HPM\CCP;
use App\Models\Master\FinishGood;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;

new
#[Title('Edit Critical Check Point')]
class extends Component {
    use WithFileUploads, Toast;

    /* ----------
    | Form Properties
    |-------------------------------------------------------------------------- */
    public ?CCP $ccp = null;
    public string $finish_good_id = '';
    public string $stage = 'ALL';
    
    #[Validate('nullable|image|mimes:jpg,jpeg,png,webp|max:2048')]
    public $check_point_img = null;
    
    public string $revision = '';
    public string $description = '';
    public bool $is_active = true;
    public bool $removeExistingImage = false;

    /* ----------
    | Search Properties
    |-------------------------------------------------------------------------- */
    public string $searchFinishGood = '';

    /* ----------
    | Lifecycle
    |-------------------------------------------------------------------------- */

    /**
     * Mount component with existing CCP data.
     */
    public function mount(string $id): void
    {
        $this->ccp = CCP::with('finishGood')->findOrFail($id);
        
        // Populate form fields
        $this->finish_good_id = $this->ccp->finish_good_id;
        $this->stage = $this->ccp->stage ?? 'ALL';
        $this->revision = $this->ccp->revision;
        $this->description = $this->ccp->description ?? '';
        $this->is_active = (bool) $this->ccp->is_active;

        Log::info('CCP Edit mounted', [
            'ccp_id' => $this->ccp->id,
            'finish_good' => $this->ccp->finishGood?->part_number
        ]);
    }

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
     * Update existing CCP record.
     */
    public function update(): void
    {
        if (!$this->ccp) {
            $this->error(__('CCP data not found.'), null, 'toast-top toast-end');
            return;
        }

        // Validate all fields
        $validated = $this->validate([
            'finish_good_id' => 'required|exists:finish_goods,id',
            'stage' => 'required|in:PRODUCTION CHECK,PDI CHECK,DELIVERY,ALL',
            'check_point_img' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'revision' => 'required|string|max:50',
            'description' => 'required|string|max:500',
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
            $imgName = $this->ccp->check_point_img;

            // Handle image removal
            if ($this->removeExistingImage && $imgName) {
                if (Storage::disk('public')->exists('hpm/ccp/' . $imgName)) {
                    Storage::disk('public')->delete('hpm/ccp/' . $imgName);
                    Log::info('CCP Image removed', ['filename' => $imgName]);
                }
                $imgName = null;
            }

            // Handle new image upload
            if ($this->check_point_img) {
                // Delete old image if exists
                if ($imgName && Storage::disk('public')->exists('hpm/ccp/' . $imgName)) {
                    Storage::disk('public')->delete('hpm/ccp/' . $imgName);
                    Log::info('CCP Old image deleted', ['filename' => $imgName]);
                }

                // Upload new image
                $imgName = time() . '_' . uniqid() . '.' . $this->check_point_img->getClientOriginalExtension();
                $this->check_point_img->storeAs('hpm/ccp', $imgName, 'public');
                
                Log::info('CCP New image uploaded', [
                    'filename' => $imgName,
                    'size' => $this->check_point_img->getSize()
                ]);
            }

            // Update CCP record
            $this->ccp->update([
                'finish_good_id' => $this->finish_good_id,
                'stage' => $this->stage,
                'check_point_img' => $imgName,
                'revision' => $this->revision,
                'description' => $this->description,
                'is_active' => $this->is_active,
            ]);

            // Invalidate cache
            Cache::forget("ccp_detail_{$this->ccp->id}");
            Cache::flush();

            Log::info('CCP updated successfully', [
                'ccp_id' => $this->ccp->id,
                'finish_good_id' => $this->finish_good_id,
                'revision' => $this->revision
            ]);

            $this->success(__('CCP updated successfully!'), null, 'toast-top toast-end');
            
            // Redirect to index
            $this->redirect(route('qa.hpm.ccp.index'), navigate: true);

        } catch (\Exception $e) {
            Log::error('Failed to update CCP', [
                'ccp_id' => $this->ccp->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->error(__('Failed to update CCP: :error', ['error' => $e->getMessage()]), null, 'toast-top toast-end');
        }
    }

    /**
     * Remove existing image flag.
     */
    public function removeImage(): void
    {
        $this->removeExistingImage = true;
        $this->success(__('Image will be removed during update.'), null, 'toast-top toast-end');
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
        title="Edit Critical Check Point" 
        subtitle="Update checkpoint information" 
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

    @if($ccp)
        {{-- FORM CARD --}}
        <x-card title="CCP Information" subtitle="Update the details below" shadow separator>
            <x-form wire:submit="update">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Left Column --}}
                    <div class="space-y-4">
                        {{-- Finish Good Select with Search --}}
                        <x-select 
                            label="Finish Good" 
                            wire:model.live="finish_good_id"
                            :options="$finishGoods"
                            placeholder="Select a finish good..."
                            searchable
                            icon="o-cube"
                            hint="Current: {{ $ccp->finishGood?->part_number ?? 'N/A' }}"
                            class="select-bordered" />
                        
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
                        {{-- Current Image Display --}}
                        <div>
                            <label class="label">
                                <span class="label-text font-semibold">Current Image</span>
                            </label>
                            @if($ccp->check_point_img && !$removeExistingImage)
                                <div class="relative inline-block">
                                    <img 
                                        src="{{ Storage::url('hpm/ccp/' . $ccp->check_point_img) }}" 
                                        alt="Current CCP Image" 
                                        class="h-40 w-auto rounded-lg shadow-md" />
                                    <x-button 
                                        icon="o-trash" 
                                        wire:click="removeImage"
                                        class="btn-error btn-sm absolute top-2 right-2"
                                        tooltip="Remove Image" />
                                </div>
                            @elseif($removeExistingImage)
                                <x-alert 
                                    title="Image will be removed" 
                                    description="The current image will be deleted when you update." 
                                    icon="o-information-circle" 
                                    class="alert-warning" />
                            @else
                                <img 
                                    src="https://placehold.co/600x400?text=Critical\nCheckpoint" 
                                    alt="No Image" 
                                    class="h-40 w-auto rounded-lg opacity-50" />
                            @endif
                        </div>

                        {{-- Image Upload with Preview --}}
                        <div>
                            <x-file 
                                label="{{ $ccp->check_point_img ? 'Change Image' : 'Upload Image' }}" 
                                wire:model="check_point_img" 
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                hint="Max 2MB. Formats: JPG, JPEG, PNG, WEBP" />
                            
                            {{-- Preview new uploaded image --}}
                            @if ($check_point_img)
                                <div class="mt-3">
                                    <label class="text-sm font-semibold text-base-content/70">New Image Preview:</label>
                                    <img src="{{ $check_point_img->temporaryUrl() }}" 
                                         class="mt-2 h-40 w-auto rounded-lg shadow-md" 
                                         alt="Preview" />
                                </div>
                            @endif
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
                        label="Update CCP" 
                        type="submit" 
                        icon="o-check"
                        spinner="update"
                        class="btn-primary" />
                </x-slot:actions>
            </x-form>
        </x-card>

        {{-- Record Info Card --}}
        <x-card title="Record Information" class="mt-4" shadow>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <label class="text-xs text-base-content/70">Record ID</label>
                    <p class="font-mono font-semibold">{{ $ccp->id }}</p>
                </div>
                <div>
                    <label class="text-xs text-base-content/70">Created At</label>
                    <p class="font-semibold">{{ $ccp->created_at?->format('d/m/Y H:i') ?? '-' }}</p>
                </div>
                <div>
                    <label class="text-xs text-base-content/70">Last Updated</label>
                    <p class="font-semibold">{{ $ccp->updated_at?->format('d/m/Y H:i') ?? '-' }}</p>
                </div>
            </div>
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
    @else
        <x-alert 
            title="Error" 
            description="CCP record not found." 
            icon="o-exclamation-triangle" 
            class="alert-error" />
    @endif
</div>
