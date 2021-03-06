<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\Response;

/**
 * Pjax is a widget integrating the [pjax](https://github.com/defunkt/jquery-pjax) jQuery plugin.
 *
 * Pjax captures the link clicks in the content enclosed between its [[begin()]] and [[end()]] calls,
 * turns them into AJAX requests, and replaces the enclosed content with the corresponding AJAX response.
 *
 * The following example makes the [[yii\gridview\GridView]] widget support updating via AJAX:
 *
 * ```php
 * use yii\widgets\Pjax;
 *
 * Pjax::begin();
 * echo GridView::widget([...]);
 * Pjax::end();
 * ```
 *
 * Clicking the sorting and pagination links in the grid will trigger AJAX-based updating of the grid content.
 * Moreover, if the grid view has turned on filtering, the filtering will also be performed via AJAX.
 *
 * By default, Pjax enables [[enablePushState|push state]], which means the browser's current URL will
 * be updated when an AJAX request is made by Pjax.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Pjax extends Widget
{
	/**
	 * @var array the HTML attributes for the widget container tag.
	 */
	public $options = [];
	/**
	 * @var string the jQuery selector of the links that should trigger pjax requests.
	 * If not set, all links within the enclosed content of Pjax will trigger pjax requests.
	 * Note that the pjax response to a link is a full page, a normal request will be sent again.
	 */
	public $linkSelector;
	/**
	 * @var boolean whether to enable push state.
	 */
	public $enablePushState = true;
	/**
	 * @var boolean whether to enable replace state.
	 */
	public $enableReplaceState = false;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		if (!isset($this->options['id'])) {
			$this->options['id'] = $this->getId();
		}

		ob_start();
		ob_implicit_flush(false);

		if ($this->requiresPjax()) {
			$view = $this->getView();
			$view->clear();
			$view->beginPage();
			$view->head();
			$view->beginBody();
		}
		echo Html::beginTag('div', $this->options);
	}

	/**
	 * @inheritdoc
	 */
	public function run()
	{
		echo Html::endTag('div');
		if ($requiresPjax = $this->requiresPjax()) {
			$view = $this->getView();
			$view->endBody();
			$view->endPage(true);
		}

		$content = ob_get_clean();

		if ($requiresPjax) {
			// only need the content enclosed within this widget
			$response = Yii::$app->getResponse();
			$level = ob_get_level();
			$response->clearOutputBuffers();
			$response->setStatusCode(200);
			$response->format = Response::FORMAT_HTML;
			$response->content = $content;
			$response->send();

			// re-enable output buffer to capture content after this widget
			for (; $level > 0; --$level) {
				ob_start();
				ob_implicit_flush(false);
			}
		} else {
			$this->registerClientScript();
			echo $content;
		}
	}

	/**
	 * @return boolean whether the current request requires pjax response from this widget
	 */
	protected function requiresPjax()
	{
		$headers = Yii::$app->getRequest()->getHeaders();
		return $headers->get('X-Pjax') && ($selector = $headers->get('X-Pjax-Container')) === '#' . $this->getId();
	}

	/**
	 * Registers the needed JavaScript.
	 */
	public function registerClientScript()
	{
		$id = $this->options['id'];
		$options = Json::encode([
			'push' => $this->enablePushState,
			'replace' => $this->enableReplaceState,
		]);
		$linkSelector = Json::encode($this->linkSelector !== null ? $this->linkSelector : '#' . $id . ' a');
		$view = $this->getView();
		PjaxAsset::register($view);
		$js = "jQuery(document).pjax($linkSelector, \"#$id\", $options);";
		$view->registerJs($js);
	}
}
