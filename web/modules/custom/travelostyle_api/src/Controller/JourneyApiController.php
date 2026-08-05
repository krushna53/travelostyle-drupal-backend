<?php

namespace Drupal\travelostyle_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Resolves a public "/journey/{slug}" alias to its node's JSON:API payload.
 */
class JourneyApiController extends ControllerBase {

  public function __construct(
    protected AliasManagerInterface $aliasManager,
    protected HttpKernelInterface $httpKernel,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path_alias.manager'),
      $container->get('http_kernel'),
    );
  }

  public function getJourney(string $slug, Request $request): Response {
    $alias = '/journey/' . rawurldecode($slug);
    $internal_path = $this->aliasManager->getPathByAlias($alias);

    if (!preg_match('#^/node/(\d+)$#', $internal_path, $matches)) {
      return new JsonResponse(['errors' => [['status' => '404', 'title' => 'Not found']]], 404);
    }

    $node = $this->entityTypeManager()->getStorage('node')->load($matches[1]);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'journey' || !$node->access('view')) {
      return new JsonResponse(['errors' => [['status' => '404', 'title' => 'Not found']]], 404);
    }

    // Reuse the existing (already public) JSON:API resource for the node so
    // the response shape stays in sync with jsonapi.settings and field
    // config, instead of hand-maintaining a separate field mapping here.
    $sub_request = Request::create(
      '/jsonapi/node/journey/' . $node->uuid(),
      'GET',
      $request->query->all(),
      $request->cookies->all(),
      [],
      $request->server->all(),
    );
    $sub_request->headers->set('Accept', 'application/vnd.api+json');
    if ($request->hasSession()) {
      $sub_request->setSession($request->getSession());
    }

    return $this->httpKernel->handle($sub_request, HttpKernelInterface::SUB_REQUEST);
  }

}
