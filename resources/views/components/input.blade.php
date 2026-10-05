@props(['label', 'name', 'value' => '', 'type' => 'text', 'required' => false])
<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">
        {{ $label }} @if($required)<span class="text-red-500" aria-hidden="true">*</span>@endif
    </label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           value="{{ old($name, $value) }}"
           @if($required) required aria-required="true" @endif
           {{ $attributes->merge(['class' => 'w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 min-h-[44px]']) }}>
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
