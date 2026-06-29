@extends('layouts.app')
@section('title', 'Builder — /' . $page->full_path)

@push('head')
    <script src="{{ asset('js/builder.js') }}" defer></script>
    <script src="{{ asset('js/form-wizard.js') }}" defer></script>
    <script src="{{ asset('js/builder-dnd.js') }}" defer></script>
    <style>
        .wb-ghost{opacity:.4;border:2px dashed #2563eb}
        [data-canvas-zone]{min-height:48px}
    </style>
@endpush

@section('content')
<div x-data="webblokBuilder({
        pageId: '{{ $page->id }}',
        locale: 'en',
        csrf: '{{ csrf_token() }}',
        definitions: {{ Illuminate\Support\Js::from($definitions->map(fn($d) => [
            'blok_key' => $d->blok_key, 'label' => $d->label, 'category' => $d->category,
            'icon' => $d->icon, 'accepts_children' => $d->accepts_children,
            'schema' => $d->schema, 'default_config' => $d->default_config,
        ])) }},
        endpoints: {
            tree: '{{ route('cms.builder.tree', $page->id) }}',
            storeBlok: '{{ route('cms.builder.bloks.store', $page->id) }}',
            persistTree: '{{ route('cms.builder.tree.persist', $page->id) }}',
            revision: '{{ route('cms.builder.revision', $page->id) }}'
        }
     })" x-init="init()" class="flex gap-4" style="height:calc(100vh - 9rem)">

    {{-- Palette --}}
    <aside class="w-56 bg-white rounded-xl shadow p-3 overflow-y-auto shrink-0">
        <h3 class="text-xs font-semibold text-slate-500 uppercase mb-2">Bloks</h3>
        <div data-palette class="space-y-1">
            <template x-for="def in definitions" :key="def.blok_key">
                <button @click="addBlok(def.blok_key, 'body')"
                        class="w-full text-left text-sm px-3 py-2 rounded-lg bg-slate-50 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2"
                        :data-blok-key="def.blok_key">
                    <span x-text="def.icon || '▦'"></span>
                    <span x-text="def.label"></span>
                </button>
            </template>
            <p x-show="definitions.length === 0" class="text-xs text-slate-400 px-3 py-2">
                No bloks installed. Run the BlokDefinitionSeeder.
            </p>
        </div>
    </aside>

    {{-- Canvas --}}
    <section class="flex-1 bg-white rounded-xl shadow flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 border-b">
            <div class="flex items-center gap-2 text-sm">
                <button @click="setDevice('desktop')" :class="device==='desktop' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100'" class="px-2 py-1 rounded">Desktop</button>
                <button @click="setDevice('tablet')" :class="device==='tablet' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100'" class="px-2 py-1 rounded">Tablet</button>
                <button @click="setDevice('mobile')" :class="device==='mobile' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100'" class="px-2 py-1 rounded">Mobile</button>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-xs text-slate-400" x-show="saving">Saving…</span>
                <span class="text-xs text-green-600" x-show="!saving && lastSaved" x-text="'Saved ' + lastSaved"></span>
                <span class="text-xs text-amber-600" x-show="dirty && !saving">Unsaved changes</span>
                <button @click="saveRevision()" class="bg-slate-800 text-white px-3 py-1.5 rounded-lg">Save Snapshot</button>
                <a :href="'{{ url('site') }}/{{ $page->full_path }}?preview=1&tenant={{ request('tenant') }}'" target="_blank"
                   class="bg-blue-600 text-white px-3 py-1.5 rounded-lg">Preview</a>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 bg-slate-50">
            <div class="mx-auto bg-white shadow-sm border rounded-lg transition-all" :style="`width:${deviceWidth()}`">
                @foreach (['header', 'body', 'sidebar', 'footer'] as $section)
                    <div class="border-b last:border-b-0">
                        <div class="px-3 py-1 text-[10px] uppercase tracking-wide text-slate-400 bg-slate-50">{{ $section }}</div>
                        <div data-canvas-zone data-section="{{ $section }}" class="p-2 space-y-2">
                            <template x-for="node in sections.{{ $section }}" :key="node.id">
                                <div @click.stop="selectBlok(node.id)"
                                     :class="selectedId === node.id ? 'ring-2 ring-blue-500' : 'hover:ring-1 hover:ring-slate-300'"
                                     class="group relative rounded-lg border border-slate-200 bg-white p-3 cursor-pointer">
                                    <div class="absolute -top-2 -left-2 opacity-0 group-hover:opacity-100 flex gap-1">
                                        <span data-drag-handle class="cursor-grab bg-slate-700 text-white text-xs px-1.5 rounded">⠿</span>
                                        <button @click.stop="removeBlok(node.id)" class="bg-red-500 text-white text-xs px-1.5 rounded">✕</button>
                                    </div>
                                    <div class="text-sm font-medium text-slate-700" x-text="(definitionFor(node.blok_key)?.label) || node.blok_key"></div>
                                    <div class="text-xs text-slate-400" x-text="node.blok_key"></div>
                                </div>
                            </template>
                            <p x-show="sections.{{ $section }}.length === 0" class="text-center text-xs text-slate-300 py-3">
                                Drop bloks here
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Inspector / Form Wizard --}}
    <aside class="w-80 bg-white rounded-xl shadow flex flex-col overflow-hidden shrink-0" x-data="webblokFormWizard()">
        <div class="px-4 py-2 border-b font-semibold text-sm">Inspector</div>
        <div class="flex-1 overflow-y-auto p-4" x-show="selected" x-cloak>
            <template x-if="selected">
                <div>
                    <div class="text-xs text-slate-400 mb-3" x-text="selected.blok_key"></div>

                    {{-- Step tabs --}}
                    <div class="flex gap-1 mb-3 flex-wrap">
                        <template x-for="(s, i) in steps(definitionFor(selected.blok_key))" :key="s.key">
                            <button @click="step = i" :class="step===i ? 'bg-blue-100 text-blue-700' : 'bg-slate-100'"
                                    class="text-xs px-2 py-1 rounded" x-text="s.label"></button>
                        </template>
                    </div>

                    {{-- Fields --}}
                    <template x-for="s in [steps(definitionFor(selected.blok_key))[step]].filter(Boolean)" :key="s.key">
                        <div class="space-y-3">
                            <template x-for="field in s.fields" :key="field.key">
                                <div x-show="visible(field, selected.config)">
                                    <label class="block text-xs font-medium text-slate-600 mb-1" x-text="field.label"></label>

                                    {{-- text / number / date --}}
                                    <template x-if="['text','number','date'].includes(field.type)">
                                        <input :type="field.type"
                                               :value="selected.config[field.key] ?? (field.default ?? '')"
                                               @input="updateConfig(field.key, $event.target.value)"
                                               class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    </template>

                                    {{-- textarea / richtext / code --}}
                                    <template x-if="['textarea','richtext','code'].includes(field.type)">
                                        <textarea rows="3"
                                                  @input="updateConfig(field.key, $event.target.value)"
                                                  class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                                  x-text="selected.config[field.key] ?? ''"></textarea>
                                    </template>

                                    {{-- toggle --}}
                                    <template x-if="field.type === 'toggle'">
                                        <input type="checkbox"
                                               :checked="selected.config[field.key] ?? false"
                                               @change="updateConfig(field.key, $event.target.checked)">
                                    </template>

                                    {{-- select / radio --}}
                                    <template x-if="['select','radio'].includes(field.type)">
                                        <select @change="updateConfig(field.key, $event.target.value)"
                                                class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                            <template x-for="opt in (field.options || [])" :key="opt">
                                                <option :value="opt" :selected="selected.config[field.key] === opt" x-text="opt"></option>
                                            </template>
                                        </select>
                                    </template>

                                    {{-- color --}}
                                    <template x-if="field.type === 'color'">
                                        <input type="color"
                                               :value="selected.config[field.key] ?? '#2563eb'"
                                               @input="updateConfig(field.key, $event.target.value)"
                                               class="h-9 w-full rounded-lg border border-slate-300">
                                    </template>

                                    {{-- range --}}
                                    <template x-if="field.type === 'range'">
                                        <input type="range" :min="field.min ?? 0" :max="field.max ?? 10"
                                               :value="selected.config[field.key] ?? (field.default ?? 0)"
                                               @input="updateConfig(field.key, parseInt($event.target.value))"
                                               class="w-full">
                                    </template>

                                    {{-- image / icon / link (simple URL inputs) --}}
                                    <template x-if="['image','icon','link'].includes(field.type)">
                                        <input type="text" :placeholder="field.type === 'link' ? 'https://…' : 'media id / icon'"
                                               :value="selected.config[field.key] ?? ''"
                                               @input="updateConfig(field.key, $event.target.value)"
                                               class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    </template>

                                    {{-- repeater --}}
                                    <template x-if="field.type === 'repeater'">
                                        <div class="space-y-2">
                                            <template x-for="(row, idx) in (selected.config[field.key] || [])" :key="idx">
                                                <div class="border rounded-lg p-2 bg-slate-50">
                                                    <template x-for="sub in field.fields" :key="sub.key">
                                                        <input type="text" :placeholder="sub.label"
                                                               x-model="row[sub.key]" @input="markDirty()"
                                                               class="w-full mb-1 rounded border border-slate-300 px-2 py-1 text-xs">
                                                    </template>
                                                    <button @click="removeRepeaterRow(selected, field, idx)" class="text-xs text-red-600">Remove</button>
                                                </div>
                                            </template>
                                            <button @click="addRepeaterRow(selected, field)" class="text-xs bg-slate-200 px-2 py-1 rounded">+ Add row</button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
        <div class="flex-1 grid place-items-center text-sm text-slate-400 p-4" x-show="!selected">
            Select a blok to edit its content.
        </div>
    </aside>
</div>
@endsection
