<?php

namespace App\Providers;

use App\Models\Order as OrderModel;
use App\Models\User;
use App\Nova\Advert;
use App\Nova\Category;
use App\Nova\Dashboards\Main;
use App\Nova\Feature;
use App\Nova\Guest;
use App\Nova\Issue;
use App\Nova\Lenses\OrdersForCollection;
use App\Nova\Lenses\OrdersToPost;
use App\Nova\Listing;
use App\Nova\Order;
use App\Nova\Page;
use App\Nova\PostageBand;
use App\Nova\Product;
use App\Nova\Promoter;
use App\Nova\PromoterAccount;
use App\Nova\Publisher;
use App\Nova\ShopCategory;
use App\Nova\ShopSettings;
use App\Nova\Stockist;
use App\Nova\Tag;
use App\Nova\Venue;
use App\Nova\VenueManager;
use App\Nova\Widget;
use App\Nova\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Laravel\Nova\Dashboard;
use Laravel\Nova\Menu\MenuGroup;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;
use Laravel\Nova\Tool;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * The Office's own scripts, each by its name and its file under public.
     *
     * @var array<string, string>
     */
    private const SCRIPTS = [
        'office-cards' => 'js/office-cards.js',
        'office-active-writer' => 'js/office-active-writer.js',
        'office-preview' => 'js/office-preview.js',
        'goblin-mode' => 'js/goblin-mode.js',
        'office-logo' => 'js/office-logo.js',
        'office-unsaved-work' => 'js/office-unsaved-work.js',
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        parent::boot();

        Nova::style(self::versioned('admin-overrides', 'css/nova-overrides.css'), public_path('css/nova-overrides.css'));

        foreach (self::SCRIPTS as $name => $file) {
            Nova::script(self::versioned($name, $file), public_path($file));
        }

        Nova::mainMenu(function (Request $request) {
            return [
                MenuSection::dashboard(Main::class)->icon('cube'),

                MenuSection::make('Content', [
                    MenuItem::resource(Issue::class),
                    MenuItem::resource(Feature::class),
                    MenuItem::resource(Page::class),
                ])->icon('document-text')->collapsable(),

                MenuSection::make('Events', [
                    MenuItem::resource(Listing::class),
                    MenuItem::resource(Promoter::class),
                    MenuItem::resource(Venue::class),
                    MenuItem::resource(Advert::class),
                    MenuItem::resource(Widget::class),
                ])->icon('bookmark-square')->collapsable(),

                MenuSection::make('Lookups', [
                    MenuItem::resource(Category::class),
                    MenuItem::resource(Tag::class),
                    MenuItem::resource(Stockist::class),
                ])->icon('book-open')->collapsable(),

                MenuSection::make('Shop', [
                    MenuItem::resource(Order::class)
                        ->withBadge(fn (): string|false => $this->ordersOutstanding(), 'success'),
                    MenuItem::lens(Order::class, OrdersToPost::class),
                    MenuItem::lens(Order::class, OrdersForCollection::class),
                    MenuItem::resource(Product::class),
                    MenuItem::resource(ShopCategory::class),
                    MenuItem::resource(PostageBand::class),
                    MenuItem::resource(ShopSettings::class),
                ])->icon('shopping-bag')->collapsable(),

                MenuSection::make('Admin', [
                    MenuGroup::make('Users', [
                        MenuItem::resource(\App\Nova\User::class)->name('All users'),
                        MenuItem::resource(Writer::class),
                        MenuItem::resource(VenueManager::class),
                        MenuItem::resource(PromoterAccount::class),
                        MenuItem::resource(Publisher::class),
                        MenuItem::resource(Guest::class),
                    ]),
                ])->icon('adjustments')->collapsable(),
            ];
        });
    }

    /**
     * How many orders are still the office's to deal with: to post, or
     * waiting to be collected.
     *
     * False rather than nought, which leaves the badge off the Orders link
     * entirely, so a badge there always means there is work to do.
     */
    /**
     * The name Nova serves one of the Office's own files under: its name and
     * the time the file last changed. Nova serves each at an address made
     * from the name, with a Last-Modified date and no lifetime, so a browser
     * could go on running its copy from before a deploy for hours, as the
     * Adverts card did on 2026-11-24. A changed file is a new address, which
     * a browser has to fetch afresh.
     */
    private static function versioned(string $name, string $file): string
    {
        return $name.'-'.filemtime(public_path($file));
    }

    protected function ordersOutstanding(): string|false
    {
        $outstanding = OrderModel::query()->awaitingOffice()->count();

        return $outstanding > 0 ? (string) $outstanding : false;
    }

    /**
     * Register the configurations for Laravel Fortify.
     */
    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                Features::updatePasswords(),
                // Features::emailVerification(),
                // Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
            ])
            ->register();
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        Nova::routes()
            ->withAuthenticationRoutes()
            ->withPasswordResetRoutes()
            ->withoutEmailVerificationRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', function (User $user) {
            return in_array($user->email, User::OFFICE_EMAILS);
        });
    }

    /**
     * Register the application's Nova resources.
     *
     * The writers, venue managers, promoters, publishers and guests are each
     * a list of users of their own, and Nova files a model under the first
     * of its resources in the alphabet - which would make Guests the home of
     * every account, in the search and the action log alike. Users stays it.
     */
    protected function resources(): void
    {
        parent::resources();

        Nova::$resourcesByModel[User::class] = \App\Nova\User::class;
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array<int, Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            new Main,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array<int, Tool>
     */
    public function tools(): array
    {
        return [
        ];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        //
    }
}
