@props(['code' => null])

@if ($code)
    <span class="fi fi-{{ $code }}"
          style="display:inline-block;width:1.3em;height:0.95em;border-radius:2px;background-size:cover;background-position:center;vertical-align:-1px;margin-right:0.45em;"
          aria-hidden="true"></span>
@endif
