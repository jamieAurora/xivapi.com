<?php

namespace App\Controller;

use App\Exception\InvalidCompanionMarketRequestException;
use App\Exception\InvalidCompanionMarketRequestServerSizeException;
use App\Service\Companion\Companion;
use App\Service\Companion\CompanionMarket;
use App\Service\Content\GameServers;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package App\Controller
 */
class MarketController extends AbstractController
{
    const DEFAULT_MAX_HISTORY = 100;
    
    /** @var CompanionMarket */
    private $companionMarket;
    /** @var Companion */
    private $companion;
    
    public function __construct(Companion $companion, CompanionMarket $companionMarket)
    {
        $this->companion = $companion;
        $this->companionMarket = $companionMarket;
    }
    
    /**
     * Obtain price + history for an item on a specific server
     *
     * @Route("/market/{server}/items/{itemId}")
     * @Route("/market/{server}/item/{itemId}")
     */
    public function itemByServer(Request $request, string $server, int $itemId)
    {
        // options
        $maxHistory = $request->get('max_history') ?: self::DEFAULT_MAX_HISTORY;
        
        // build response
        $serverId = GameServers::getServerId($server);
        $response = $this->companionMarket->get($serverId, $itemId, $maxHistory);
        
        return $this->json($response);
    }
    
    /**
     * Obtain price + history for an item on multiple servers
     *
     * @Route("/market/item/{itemId}")
     */
    public function item(Request $request, int $itemId, bool $isInternal = false)
    {
        $servers = array_filter(explode(',', $request->get('servers')));
        $dc      = ucwords($request->get('dc'));

        if (count($servers) > 15) {
            throw new \Exception("No, too many servers!");
        }
    
        // overwrite servers if a DC is provided
        $servers = $dc ? GameServers::LIST_DC[$dc] : $servers;
        
        // server or dc is empty
        if (empty($servers) && empty($dc)) {
            throw new InvalidCompanionMarketRequestException();
        }
        
        // too many servers
        if (count($servers) > InvalidCompanionMarketRequestServerSizeException::MAX_SERVERS) {
            throw new InvalidCompanionMarketRequestServerSizeException();
        }
        
        // options
        $maxHistory = $request->get('max_history') ?: self::DEFAULT_MAX_HISTORY;

        // build response
        $response = [];
        foreach ($servers as $server) {
            $serverId = is_string($server) ? GameServers::getServerId($server) : $server;
            $response[$server] = $this->companionMarket->get($serverId, $itemId, $maxHistory);
        }

        if ($isInternal) {
            return $response;
        }

        return $this->json($response);
    }

    /**
     * Obtain price + history for multiple items
     *
     * @Route("/market/items")
     */
    public function itemMulti(Request $request)
    {
        $itemIds = array_filter(explode(',', $request->get('ids')));
        $results = [];

        if (count($itemIds) > 100) {
            throw new \Exception("No, too many ids!");
        }

        foreach ($itemIds as $id) {
            $results[] = $this->item($request, $id, true);
        }

        return $this->json($results);
    }
    
    /**
     * @Route("/market/search")
     */
    public function search(Request $request)
    {
        return $this->json(
            $this->companionMarket->search()
        );
    }
    
    /**
     * @Route("/market/categories")
     */
    public function categories()
    {
        return $this->json(
            $this->companion->getCategories()
        );
    }
}
