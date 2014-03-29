<?php

class TCPDFStylesheet {

	protected $empty = true;

	protected $list_id = array();
	protected $list_class = array();
	protected $list_tag = array();
	protected $list_general = array();

	public function is_empty(){
		return $this->empty;
	}

	public function add_css($css){
		$css = TCPDF_STATIC::extractCSSproperties($css);

		foreach($css as $selector => $style) {
			$match = array();
			if (preg_match('/#([\w-]*)(\[[^\]]+\])*(::?[\w-]+)*$/s', $selector, $match)){
				$id = $match[1];
				if (!isset($this->list_id[$id])){
					$this->list_id[$id] = array();
				}
				$list = &$this->list_id[$id];
			} else if (preg_match('/\.([\w-]*)(\[[^\]]+\])*(::?[\w-]+)*$/s', $selector, $match)){
				$class = $match[1];
				if (!isset($this->list_class[$class])){
					$this->list_class[$class] = array();
				}
				$list = &$this->list_class[$class];
			} else if (preg_match('/[\>\+\~\s]([\w]+)(\[[^\]]+\])*(::?[\w-]+)*$/', $selector, $match) ){
				$tag = $match[1];
				if (!isset($this->list_tag[$tag])){
					$this->list_tag[$tag] = array();
				}
				$list = &$this->list_tag[$tag];
			} else {
				$list = &$this->list_general;
			}

			if (isset($list[$selector])) {
				$list[$selector] .= $style;
			} else {
				$list[$selector] = $style;
			}

			unset($list);

			$this->empty = false;
		}
	}

	public function getCSSdataArray($dom, $key){
		$node = &$dom[$key];

		$cssarray = array(); // style to be returned
		// get parent CSS selectors
		$selectors = array();
		if (isset($dom[($node['parent'])]['csssel'])) {
			$selectors = $dom[($node['parent'])]['csssel'];
		}

		$search_list = function($css) use ($dom, $key, &$cssarray, &$selectors) {
			// get all styles that apply
			foreach($css as $selectorkey => $style) {
				$pos = strpos($selectorkey, ' ');
				// get specificity
				$specificity = substr($selectorkey, 0, $pos);
				// get selector
				$selector = substr($selectorkey, $pos);
				// add style if not already added on parent selector
				if (!in_array($selectorkey, $selectors)) {
					// check if this selector apply to current tag
					if (TCPDF_STATIC::isValidCSSSelectorForTag($dom, $key, $selector)) {
						$cssarray[] = array('k' => $selector, 's' => $specificity, 'c' => $style);
						$selectors[] = $selectorkey;
					}
				}
			}
		};

		$search_list($this->list_general);

		$tag = $node['value'];
		if (isset($this->list_tag[$tag])) {
			$search_list($this->list_tag[$tag]);
		}

		if (@$node['attribute']['class']) {
			$classes = explode(' ', strtolower($node['attribute']['class']));
			foreach ($classes as $class) {
				if (isset($this->list_class[$class])) {
					$search_list($this->list_class[$class]);
				}
			}
		}

		if (@$node['attribute']['id']) {
			$id = strtolower($node['attribute']['id']);
			if (isset($this->list_id[$id])) {
				$search_list($this->list_id[$id]);
			}
		}

		if (isset($node['attribute']['style'])) {
			// attach inline style (latest properties have high priority)
			
			$style = $node['attribute']['style'];
			if (stripos($style,'!important')!==false) {
				$style_important = preg_replace('/(^|;)((?!\\!important).)*(;\\n?|$)/sui','$1',$style);
				$style_important = preg_replace('/\s*!important\b/sui','',$style_important);
				$style = preg_replace('/[^;]*!important\s*(;\\n?|$)/sui','',$style);
			} else {
				$style_important = false;
			}

			if ($style) {
				$cssarray[] = array('k' => '', 's' => '1000', 'c' => $style);
			}
			if ($style_important !== false) {
				$cssarray[] = array('k' => '', 's' => '1001000', 'c' => $style_important);
			}
		}
		// order the css array to account for specificity
		$cssordered = array();
		foreach ($cssarray as $key => $val) {
			$skey = sprintf('%04d', $key);
			$cssordered[$val['s'].'_'.$skey] = $val;
		}
		// sort selectors alphabetically to account for specificity
		ksort($cssordered, SORT_STRING);
		return array($selectors, $cssordered);
	}

};
