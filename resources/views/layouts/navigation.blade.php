<nav x-data="{ open: false }" class="bg-brand-navy">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <x-application-logo class="block h-8 w-auto fill-current text-white" />
                        <span class="font-display font-semibold text-lg tracking-wide text-white">{{ __('World Cup Pool') }}</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('pools.index')" :active="request()->routeIs('pools.*')">
                        {{ __('Pools') }}
                    </x-nav-link>
                    @if (Auth::user()->is_admin)
                        <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                            {{ __('Admin') }}
                        </x-nav-link>
                        @php($pendingPools = \App\Models\Pool::whereNull('approved_at')->count())
                        <x-nav-link :href="route('admin.pools.index')" :active="request()->routeIs('admin.pools.*')">
                            {{ __('Approvals') }}@if ($pendingPools) <span class="ml-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-red-100 text-red-700">{{ $pendingPools }}</span>@endif
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown + Language Switcher -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-3">
                {{-- Language switcher --}}
                <div class="flex items-center gap-1">
                    <form method="POST" action="{{ route('locale.switch', 'en') }}">
                        @csrf
                        <button type="submit"
                                class="flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded transition
                                       {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-white/10' }}">
                            <span class="fi fi-us" style="width:1.1em;height:0.85em;display:inline-block;border-radius:2px;background-size:cover;background-position:center;"></span>
                            EN
                        </button>
                    </form>
                    <form method="POST" action="{{ route('locale.switch', 'es') }}">
                        @csrf
                        <button type="submit"
                                class="flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded transition
                                       {{ app()->getLocale() === 'es' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-white/10' }}">
                            <span class="fi fi-mx" style="width:1.1em;height:0.85em;display:inline-block;border-radius:2px;background-size:cover;background-position:center;"></span>
                            ES
                        </button>
                    </form>
                </div>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-200 bg-transparent hover:text-white focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-200 hover:text-white hover:bg-brand-navy-light focus:outline-none focus:bg-brand-navy-light focus:text-white transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        {{-- Language switcher (mobile) --}}
        <div class="px-4 pt-3 pb-1 flex items-center gap-2">
            <form method="POST" action="{{ route('locale.switch', 'en') }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded transition
                               {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-white/10' }}">
                    <span class="fi fi-us" style="width:1.1em;height:0.85em;display:inline-block;border-radius:2px;background-size:cover;background-position:center;"></span>
                    EN
                </button>
            </form>
            <form method="POST" action="{{ route('locale.switch', 'es') }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded transition
                               {{ app()->getLocale() === 'es' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-white/10' }}">
                    <span class="fi fi-mx" style="width:1.1em;height:0.85em;display:inline-block;border-radius:2px;background-size:cover;background-position:center;"></span>
                    ES
                </button>
            </form>
        </div>

        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('pools.index')" :active="request()->routeIs('pools.*')">
                {{ __('Pools') }}
            </x-responsive-nav-link>
            @if (Auth::user()->is_admin)
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                    {{ __('Admin') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.pools.index')" :active="request()->routeIs('admin.pools.*')">
                    {{ __('Pool Approvals') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-brand-navy-light">
            <div class="px-4">
                <div class="font-medium text-base text-white">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-300">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
