@props(['label', 'name', 'value' => '', 'options' => [], 'required' => false])
{{-- $options: ['value' => 'Label'] --}}
<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">
        {{ $label }} @if($required)<span class="text-red-500" aria-hidden="true">*</span>@endif
    </label>
    <select id="{{ $name }}" name="{{ $name }}"
            @if($required) required aria-required="true" @endif
            {{ $attributes->merge(['class' => 'w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 min-h-[44px] bg-white']) }}>
        @foreach ($options as $val => $text)
            <option value="{{ $val }}" @selected((string) old($name, $value) === (string) $val)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
