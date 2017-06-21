<?php

namespace ellsif;

use ellsif\util\StringUtil;
use Goutte\Client;
use GuzzleHttp\Psr7;
use Symfony\Component\DomCrawler\Crawler;

class WebPageScout
{

    protected $baseUri = '';
    protected $selfLinks = [];
    protected $innerLinks = [];
    protected $outerLinks = [];
    protected $cssList = [];
    protected $jsList = [];
    protected $imageList = [];

    protected $title = '';
    protected $keyword = '';
    protected $description = '';

    /**
     * 全ページ走査
     */
    public function scout($url, &$resultList = [])
    {
        $url = $this->trimIndex($url);
        if (array_key_exists($url, $resultList) || count($resultList) > 200) {
            return;
        }

        $result = $this->scoutPage($url);
        $resultList[$url] = $result;
        foreach($result['innerLinks'] ?? [] as $link) {
            $this->scout($link, $resultList);
        }
        return $resultList;
    }

    /**
     * 1ページ走査
     */
    public function scoutPage($url)
    {
        $client = new Client();
        $crawler = $client->request('GET', $url);

        $this->baseUri = $url;
        $this->selfLinks = [];
        $this->innerLinks = [];
        $this->outerLinks = [];
        $this->cssList = [];
        $this->jsList = [];
        $this->imageList = [];
        $this->title = '';
        $this->keyword = '';
        $this->description = '';

        // タイトル、キーワード、デスクリプション取得
        $node = $crawler->filter('head > title');
        $this->title = ($node->count()) ? $node->text() : '';
        $crawler->filter('head > meta')->each(function(Crawler $node) {
            $name = $node->attr('name');
            if (strcasecmp($name, 'keyword') === 0) {
                $this->keyword = $node->attr('content');
            } elseif(strcasecmp($name, 'description') === 0) {
                $this->description = $node->attr('content');
            }
        });

        // リンク
        $crawler->filter('a')->each(function(Crawler $node) {
            $href = $node->attr('href');
            if (StringUtil::startsWith($href, '#')) {
                $this->addLink($this->selfLinks, $href);
            } elseif ($this->isOuterLink($href)) {
                $this->addLink($this->outerLinks, $href);
            } elseif ($href) {
                $this->addLink($this->innerLinks, $href);
            }
        });

        // CSS
        $crawler->filter('head > link')->each(function(Crawler $node) {
            $href = $node->attr('href');
            $isCss = StringUtil::endsWith(strtolower($href), '.css');
            if ($isCss && !$this->isOuterLink($href)) {
                $this->addCss($href);
            }
        });

        // JS
        $crawler->filter('head > script')->each(function(Crawler $node) {
            $href = $node->attr('src');
            $isJs = StringUtil::endsWith(strtolower($href), '.js');
            if ($isJs && !$this->isOuterLink($href)) {
                $this->addJs($href);
            }
        });

        // 画像
        $crawler->filter('img')->each(function(Crawler $node) {
            $href = $node->attr('src');
            if (!$this->isOuterLink($href)) {
                $this->addImage($href);
            }
        });

        // body
        $body = $crawler->filter('body')->html();
        $body = str_replace([' ', "\t", PHP_EOL], '', $body);

        return [
            'selfLinks' => $this->selfLinks,
            'innerLinks' => $this->innerLinks,
            'outerLinks' => $this->outerLinks,
            'cssList' => $this->cssList,
            'jsList' => $this->jsList,
            'imageList' => $this->imageList,
            'title' => $this->title,
            'keyword' => $this->keyword,
            'description' => $this->description,
            'bodySize' => mb_strlen($body),
        ];
    }

    protected function addLink(&$list, $href)
    {
        $url = $this->getAbsoluteUrl($href);
        $url = $this->trimIndex($url);
        if ($url && $this->isPage($url) && !in_array($url, $list)) {
            $list[] = $url;
        }
    }

    protected function addCss($href)
    {
        $url = $this->getAbsoluteUrl($href);
        if (!in_array($url, $this->cssList)) {
            $this->cssList[] = $url;
        }
    }

    protected function addJs($href)
    {
        $url = $this->getAbsoluteUrl($href);
        if (!in_array($url, $this->jsList)) {
            $this->jsList[] = $url;
        }
    }

    protected function addImage($href)
    {
        $url = $this->getAbsoluteUrl($href);
        if (!in_array($url, $this->imageList)) {
            $this->imageList[] = $url;
        }
    }

    protected function getAbsoluteUrl($uri)
    {
        $uri = Psr7\uri_for($uri === null ? '' : $uri);
        $uri = Psr7\UriResolver::resolve(Psr7\uri_for($this->baseUri), $uri);
        $uri = $uri->getScheme() === '' && $uri->getHost() !== '' ? $uri->withScheme('http') : $uri;
        if (in_array($uri->getScheme(), ['http', 'https'])) {
            return $uri->getScheme() . '://' . $uri->getAuthority() . $uri->getPath();
        }
        return '';
    }

    protected function isPage($url)
    {
        $allow = ['html', 'htm', 'php', 'cgi'];
        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        return (!$ext || in_array($ext, $allow));
    }

    protected function trimIndex($url)
    {
        $url = $this->rightRemove($url, '/index.html');
        $url = $this->rightRemove($url, '/index.htm');
        $url = $this->rightRemove($url, '/index.php');
        $url = rtrim($url, '/');
        return $url;
    }

    protected function isOuterLink($url)
    {
        return (!StringUtil::startsWith($url, $this->baseUri)) &&
            (
                StringUtil::startsWith($url, 'http://') ||
                StringUtil::startsWith($url, 'https://') ||
                StringUtil::startsWith($url, '//'
            )
        );
    }

    private function rightRemove(string $str, string $suffix)
    {
        if (($pos = mb_strpos($str, $suffix))) {
            return mb_substr($str, 0, $pos);
        }
        return $str;
    }
}