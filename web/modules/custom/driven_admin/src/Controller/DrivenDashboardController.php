<?php

namespace Drupal\driven_admin\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for Driven admin pages.
 */
class DrivenDashboardController extends ControllerBase {

  /**
   * Landing dashboard with quick links.
   */
  public function dashboard() {
    $items = [
      // UPDATED: point directly to the entity collection route.
      [
        'title' => $this->t('Manage All Motorcycles'),
        'route' => 'entity.driven_motorcycle.collection',
        'path'  => '/admin/content/driven-motorcycles',
        'desc'  => $this->t('Motorcycle catalog management'),
      ],
      [
        'title' => $this->t('Manage Slider(s)'),
        'route' => 'driven_admin.sliders',
        'path'  => '/admin/driven/sliders',
        'desc'  => $this->t('Homepage/landing slider management'),
      ],
      [
        'title' => $this->t('Dealer Applications'),
        'route' => 'driven_admin.dealer_apps',
        'path'  => '/admin/driven/dealer-applications',
        'desc'  => $this->t('Review and process dealer apps'),
      ],
      [
        'title' => $this->t('Contact Form Submissions'),
        'route' => 'driven_admin.contact_submissions',
        'path'  => '/admin/driven/contact-submissions',
        'desc'  => $this->t('View messages from contact forms'),
      ],
      [
        'title' => $this->t('Registration Codes'),
        'route' => 'driven_admin.registration_codes',
        'path'  => '/admin/driven/registration-codes',
        'desc'  => $this->t('Manage registration codes'),
      ],
      [
        'title' => $this->t('Import from CSV'),
        'route' => 'driven_admin.import_csv',
        'path'  => '/admin/driven/import-csv',
        'desc'  => $this->t('Data import tools'),
      ],
      [
        'title' => $this->t('Server Panel'),
        'route' => 'driven_admin.server_panel',
        'path'  => '/admin/driven/server-panel',
        'desc'  => $this->t('Environment, cache, and status'),
      ],
    ];

    $build = [
      '#title' => $this->t('Driven Menu'),
      'intro' => ['#markup' => '<p>'.$this->t('Quick access to site-specific tools.').'</p>'],
      'grid' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['driven-admin-grid']],
      ],
      '#attached' => [],
    ];

    foreach ($items as $item) {
      $url = $this->safeUrl($item['route'], $item['path'] ?? '/admin/driven');
      $build['grid'][] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['driven-admin-card']],
        'title' => [
          '#type' => 'link',
          '#title' => $item['title'],
          '#url' => $url,
          '#attributes' => ['class' => ['driven-admin-card__title']],
        ],
        'desc' => [
          '#markup' => '<div class="driven-admin-card__desc">' . $item['desc'] . '</div>',
        ],
      ];
    }

    return $build;
  }

  /**
   * Safe Url builder: use route if it exists; otherwise fall back to a path.
   */
  private function safeUrl(string $route, string $fallback_path) : Url {
    try {
      // Throws if route does not exist.
      \Drupal::service('router.route_provider')->getRouteByName($route);
      return Url::fromRoute($route);
    }
    catch (\Throwable $e) {
      return Url::fromUserInput($fallback_path);
    }
  }

  /**
   * Generic redirect helper for menu items that point to existing pages.
   *
   * Looks for a _driven_target default on the route and redirects there.
   */
  public function redirectTarget() : RedirectResponse {
    $route = \Drupal::routeMatch()->getRouteObject();
    $target = $route ? ($route->getDefault('_driven_target') ?? '/admin/driven') : '/admin/driven';
    return new RedirectResponse($target);
  }

}
