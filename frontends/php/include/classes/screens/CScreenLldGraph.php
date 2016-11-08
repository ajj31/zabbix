<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Shows surrogate screen filled with graphs generated by selected graph prototype or preview of graph prototype.
 */
class CScreenLldGraph extends CScreenLldGraphBase {

	/**
	 * @var array
	 */
	protected $createdGraphIds = [];

	/**
	 * @var array
	 */
	protected $graphPrototype = null;

	/**
	 * Returns screen items for surrogate screen.
	 *
	 * @return array
	 */
	protected function getSurrogateScreenItems() {
		$createdGraphIds = $this->getCreatedGraphIds();
		return $this->getGraphsForSurrogateScreen($createdGraphIds);
	}

	/**
	 * Retrieves graphs created for graph prototype given as resource for this screen item
	 * and returns array of the graph IDs.
	 *
	 * @return array
	 */
	protected function getCreatedGraphIds() {
		if (!$this->createdGraphIds) {
			$graphPrototype = $this->getGraphPrototype();

			if ($graphPrototype) {
				// Get all created (discovered) graphs for host of graph prototype.
				$allCreatedGraphs = API::Graph()->get([
					'output' => ['graphid', 'name'],
					'hostids' => [$graphPrototype['discoveryRule']['hostid']],
					'selectGraphDiscovery' => ['graphid', 'parent_graphid'],
					'expandName' => true,
					'filter' => ['flags' => ZBX_FLAG_DISCOVERY_CREATED],
				]);

				// Collect those graph IDs where parent graph is graph prototype selected for
				// this screen item as resource.
				foreach ($allCreatedGraphs as $graph) {
					if ($graph['graphDiscovery']['parent_graphid'] == $graphPrototype['graphid']) {
						$this->createdGraphIds[$graph['graphid']] = $graph['name'];
					}
				}
				natsort($this->createdGraphIds);
				$this->createdGraphIds = array_keys($this->createdGraphIds);
			}
		}

		return $this->createdGraphIds;
	}

	/**
	 * Makes graph screen items from given graph IDs.
	 *
	 * @param array $graphIds
	 *
	 * @return array
	 */
	protected function getGraphsForSurrogateScreen(array $graphIds) {
		$screenItemTemplate = $this->getScreenItemTemplate(SCREEN_RESOURCE_GRAPH);

		$screenItems = [];
		foreach ($graphIds as $graphId) {
			$screenItem = $screenItemTemplate;

			$screenItem['resourceid'] = $graphId;
			$screenItem['screenitemid'] = $graphId;

			$screenItems[] = $screenItem;
		}

		return $screenItems;
	}

	/**
	 * Resolves and retrieves effective graph prototype used in this screen item.
	 *
	 * @return array|bool
	 */
	protected function getGraphPrototype() {
		if ($this->graphPrototype === null) {
			$resourceid = array_key_exists('real_resourceid', $this->screenitem)
				? $this->screenitem['real_resourceid']
				: $this->screenitem['resourceid'];

			$options = [
				'output' => ['graphid', 'name', 'graphtype', 'show_legend', 'show_3d', 'templated'],
				'selectDiscoveryRule' => ['hostid']
			];

			/*
			 * If screen item is dynamic or is templated screen, real graph prototype is looked up by "name"
			 * used as resource ID for this screen item and by current host.
			 */
			if ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && $this->hostid) {
				$currentGraphPrototype = API::GraphPrototype()->get([
					'output' => ['name'],
					'graphids' => [$resourceid]
				]);
				$currentGraphPrototype = reset($currentGraphPrototype);

				$options['hostids'] = [$this->hostid];
				$options['filter'] = ['name' => $currentGraphPrototype['name']];
			}
			// otherwise just use resource ID given to this screen item.
			else {
				$options['graphids'] = [$resourceid];
			}

			$selectedGraphPrototype = API::GraphPrototype()->get($options);
			$this->graphPrototype = reset($selectedGraphPrototype);
		}

		return $this->graphPrototype;
	}

	/**
	 * Returns output for preview of graph prototype.
	 *
	 * @return CTag
	 */
	protected function getPreviewOutput() {
		$graphPrototype = $this->getGraphPrototype();

		switch ($graphPrototype['graphtype']) {
			case GRAPH_TYPE_NORMAL:
			case GRAPH_TYPE_STACKED:
				$url = 'chart3.php';
				break;

			case GRAPH_TYPE_EXPLODED:
			case GRAPH_TYPE_3D_EXPLODED:
			case GRAPH_TYPE_3D:
			case GRAPH_TYPE_PIE:
				$url = 'chart7.php';
				break;

			default:
				show_error_message(_('Graph prototype not found.'));
				exit;
		}

		$graphPrototypeItems = API::GraphItem()->get([
			'output' => [
				'gitemid', 'itemid', 'sortorder', 'flags', 'type', 'calc_fnc', 'drawtype', 'yaxisside', 'color'
			],
			'graphids' => [$graphPrototype['graphid']]
		]);

		$queryParams = [
			'items' => $graphPrototypeItems,
			'graphtype' => $graphPrototype['graphtype'],
			'period' => 3600,
			'legend' => $graphPrototype['show_legend'],
			'graph3d' => $graphPrototype['show_3d'],
			'width' => $this->screenitem['width'],
			'height' => $this->screenitem['height'],
			'name' => $graphPrototype['name']
		];

		$url .= '?'.http_build_query($queryParams);

		return new CSpan(new CImg($url));
	}
}
