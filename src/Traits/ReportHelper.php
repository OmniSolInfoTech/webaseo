<?php

namespace Osit\Webaseo\Traits;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

trait ReportHelper
{
    /**
     * Gets request proxy if set
     *
     * @return \Illuminate\Config\Repository|\Illuminate\Contracts\Foundation\Application|\Illuminate\Foundation\Application|mixed|string|null
     */
    function getRequestProxy()
    {
        if (!empty(config('webaseo.settings.request_proxy'))) {
            if (config('webaseo.settings.request_cached_proxy')) {
                $proxy = config('webaseo.settings.request_cached_proxy');
            } else {
                $proxies = preg_split('/\n|\r/', config('webaseo.settings.request_proxy'), -1, PREG_SPLIT_NO_EMPTY);
                $proxy = $proxies[array_rand($proxies)];
                config(['settings.request_cached_proxy' => $proxy]);
            }

            return $proxy;
        }

        return null;
    }

    /**
     * Validates if request is valid
     *
     * @param Request $request
     * @return void
     */
    public function validateRequestUrl(Request $request)
    {
        try {
            $request->requestReport = (new HttpClient())->request('GET', str_replace('https://', 'http://', $request["url"]), [
                'proxy' => [
                    'http' => $this->getRequestProxy(),
                    'https' => $this->getRequestProxy()
                ],
                'timeout' => config('webaseo.settings.request_timeout'),
                'allow_redirects' => [
                    'max'             => 10,
                    'strict'          => true,
                    'referer'         => true,
                    'protocols'       => ['http', 'https'],
                    'track_redirects' => true
                ],
                'headers' => [
                    'Accept-Encoding' => 'gzip, deflate',
                    'User-Agent' => config('webaseo.settings.request_user_agent')
                ],
                'on_stats' => function (TransferStats $stats) use (&$request) {
                    if ($stats->hasResponse()) {
                        $request->requestReportTransferStats = $stats;
                    }
                }
            ]);
        }  catch (GuzzleException $e) {
            $this->requestErrorMessage = match (explode(":", strtolower($e->getMessage()))[0]) {
                "curl error 3" => "Host <strong>" . $request["url"] . "</strong> is a mulformed URL.",
                "curl error 6" => "Host <strong>" . $request["url"] . "</strong> could not be resolved.",
                "curl error 7" => "Could not connect to host <strong>" . $request["url"] . "</strong> .",
                default => "Error connecting to host <strong>" . $request["url"] . "</strong> .",
            };
        }
    }

    /**
     * Evaluates robots.txt on site
     *
     * @param $value
     * @return string
     */
    private function formatRobotsRule($value): string
    {
        $replaceBeforeQuote = ['*' => '_ASTERISK_WILDCARD_', '$' => '_DOLLAR_WILDCARD_'];

        $replaceAfterQuote = ['_ASTERISK_WILDCARD_' => '.*', '_DOLLAR_WILDCARD_' => '$'];

        return '/^' . str_replace(array_keys($replaceAfterQuote), array_values($replaceAfterQuote), preg_quote(str_replace(array_keys($replaceBeforeQuote), array_values($replaceBeforeQuote), $value), '/')) . '/';
    }

    /**
     * Modifies $text
     *
     * @param $text
     * @return string
     */
    private function moldText($text): string
    {
        return trim(preg_replace('/(?:\s{2,}+|[^\S ])/', ' ', $text));
    }

    /**
     * Modifies $url
     *
     * @param $url
     * @return array|string
     */
    private function moldUrl($url): array|string
    {
        $url = str_replace(['\\?', '\\&', '\\#', '\\~', '\\;'], ['?', '&', '#', '~', ';'], $url);

        if (mb_strpos($url, '#') !== false) {
            $url = mb_substr($url, 0, mb_strpos($url, '#'));
        }

        if (mb_strpos($url, 'http://') === 0) {
            return $url;
        }

        if (mb_strpos($url, 'https://') === 0) {
            return $url;
        }

        if (mb_strpos($url, '//') === 0) {
            return parse_url($this->url, PHP_URL_SCHEME).'://'.trim($url, '/');
        }

        if (mb_strpos($url, '/') === 0) {
            return rtrim(parse_url($this->url, PHP_URL_SCHEME).'://'.parse_url($this->url, PHP_URL_HOST), '/').'/'.ltrim($url, '/');
        }

        if (mb_strpos($url, 'data:image') === 0) {
            return $url;
        }

        if (mb_strpos($url, 'tel') === 0) {
            return $url;
        }

        if (mb_strpos($url, 'mailto') === 0) {
            return $url;
        }

        return rtrim(parse_url($this->url, PHP_URL_SCHEME).'://'.parse_url($this->url, PHP_URL_HOST), '/').'/'.ltrim($url, '/');
    }

    /**
     * Check is $url is an internal URL
     *
     * @param $url
     * @return bool
     */
    private function isInternalUrl($url): bool
    {
        if (mb_strpos(parse_url($url, PHP_URL_HOST), parse_url($this->url, PHP_URL_HOST)) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Does the heavy lifting, builds report
     *
     * @param $requestReport
     * @param $requestReportStats
     * @param $report
     * @return array
     * @throws GuzzleException
     */
    private function buildReport($requestReport, $requestReportStats): array
    {
        $error = false;

        // If there is a request report, i.e. valid URL
        if ($requestReport) {
            $reportResponse = $requestReport->getBody()->getContents();
            $requestReportStats = $requestReportStats->getHandlerStats();

            $this->url = $requestReportStats['url'];

            $domDocument = new \DOMDocument();
            libxml_use_internal_errors(true);

            if (str_starts_with($reportResponse, "\xEF\xBB\xBF")) {
                $reportResponse = str_replace("\xEF\xBB\xBF", '', $reportResponse);
            }

            $domDocument->loadHTML('<?xml encoding="utf-8" ?>' . $reportResponse ?? null);

            $pageText = $this->moldText($domDocument->getElementsByTagName('body')->item(0)->textContent ?? null);

            $bodyKeyWords = array_filter(explode(' ', preg_replace('/[^\w]/ui', ' ', mb_strtolower($pageText))));


            $this->runTitle($domDocument, $requestReportStats);

            $this->runMetaDescription($domDocument);

            $this->runHeadings($domDocument);

            $this->runTitleKeywords($bodyKeyWords, $requestReportStats);

            $this->runImageAlts($domDocument);

            $this->runPageLinks($domDocument);

            $this->runHttpEncryption($requestReportStats);

            $this->run404Page();

            $this->runRobots();

            $this->runNoIndex($domDocument);

            $this->runLanguage($domDocument);

            $this->runFavIcon($domDocument);

            $this->runMixedContent($domDocument);

            $this->runCrossOrigins($domDocument);

            $this->runPlainTextEmails($reportResponse);

            $this->runHttpRequests($domDocument);

            $this->runImageFormats($domDocument, $reportResponse);

            $this->runDeferJavaScript($domDocument);

            $this->runDomSize($domDocument);

            $this->runStructuredData($domDocument);

            $this->runMetaViewport($domDocument);

            $this->runCharset($domDocument);

            $this->runTextRatio();

            $this->runDeprecatedHtmlTags($domDocument);

            $this->runSocial($domDocument);

            $this->runInlineCss($domDocument);

            $this->runPerformance($requestReportStats, $requestReport);

            $this->runSecurity($requestReport);


            $totalPoints = 0;
            foreach (array_keys($this->data['result']) as $key) {
                $totalPoints = $totalPoints + config('webaseo.settings.report_score_' . $this->data['result'][$key]['importance']);
            }

            $this->report["results"] = mb_convert_encoding($this->data['result'], 'UTF-8', 'UTF-8');
            $this->report["overallResult"] = (($this->getScore($this->report["results"]) / $this->getTotalScore($this->report["results"])) * 100);
            $this->report["generated_at"] = Carbon::now();
        }
        else {
            $error = true;
        }

        return ["report" => $this->report, "error" => $error];
    }

    /**
     * Total possible score
     *
     * @param $results
     * @return mixed
     */
    public function getTotalScore($results): mixed
    {
        $points = 0;
        foreach (array_keys($results) as $key) {
            $points += config('webaseo.settings.report_score_' . $results[$key]["importance"]);
        }

        return $points;
    }

    /**
     * Sums a core for the page
     *
     * @param $results
     * @return mixed
     */
    public function getScore($results): mixed
    {
        $points = 0;
        foreach (array_keys($results) as $key) {
            if ($results[$key]["passed"]) {
                $points += config('webaseo.settings.report_score_' . $results[$key]["importance"]);
            }
        }

        return $points;
    }


    /**
     * Grades the page title
     *
     * @param $domDocument
     * @param $requestReportStats
     * @return void
     */
    private function runTitle($domDocument, $requestReportStats): void
    {
        $titleTagsCount = 0;
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('title') as $titleNode) {
                $this->title .= $this->moldText($titleNode->textContent);
                $titleTagsCount++;
            }
        }

        $this->data['result']['title'] = [
            'passed' => true,
            'importance' => 'high',
            'value' => $this->title
        ];

        if (!$this->title) {
            $this->data['result']['title']['passed'] = false;
            $this->data['result']['title']['error']['missing'] = null;
        }

        $this->data['result']['page_size'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => $requestReportStats['size_download']
        ];

        if ($requestReportStats['size_download'] > config('webaseo.settings.report_limit_page_size')) {
            $this->data['result']['page_size']['passed'] = false;
            $this->data['result']['page_size']['error']['too_large'] = ['max' => config('webaseo.settings.report_limit_page_size')];
        }

        if (mb_strlen($this->title) < config('webaseo.settings.report_limit_min_title') || mb_strlen($this->title) > config('webaseo.settings.report_limit_max_title')) {
            $this->data['result']['title']['passed'] = false;
            $this->data['result']['title']['error']['length'] = ['min' => config('webaseo.settings.report_limit_min_title'), 'max' => config('webaseo.settings.report_limit_max_title')];
        }

        if ($titleTagsCount > 1) {
            $this->data['result']['title']['passed'] = false;
            $this->data['result']['title']['error']['too_many'] = null;
        }

    }

    /**
     * Grades the meta description
     *
     * @param $domDocument
     * @return void
     */
    private function runMetaDescription($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if (strtolower($node->getAttribute('name')) == 'description' && $this->moldText($node->getAttribute('content'))) {
                    $this->metaDescription = $this->moldText($node->getAttribute('content'));
                }
            }
        }

        $this->data['result']['meta_description'] = [
            'passed' => true,
            'importance' => 'high',
            'value' => $this->metaDescription
        ];

        if (!$this->metaDescription) {
            $this->data['result']['meta_description']['passed'] = false;
            $this->data['result']['meta_description']['error']['missing'] = null;
        }
    }

    /**
     * Grades headings in the page
     *
     * @param $domDocument
     * @return void
     */
    private function runHeadings($domDocument): void
    {
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $heading) {
            foreach ($domDocument->getElementsByTagName($heading) as $node) {
                $this->headings[$heading][] = $this->moldText($node->textContent);
            }
        }

        $this->data['result']['headings'] = [
            'passed' => true,
            'importance' => 'high',
            'value' => $this->headings
        ];

        if (!isset($this->headings['h1'])) {
            $this->data['result']['headings']['passed'] = false;
            $this->data['result']['headings']['error']['missing'] = null;
        }

        if (isset($this->headings['h1']) && count($this->headings['h1']) > 1) {
            $this->data['result']['headings']['passed'] = false;
            $this->data['result']['headings']['error']['too_many'] = null;
        }

        if (isset($this->headings['h1'][0]) && $this->headings['h1'][0] == $this->title) {
            $this->data['result']['headings']['passed'] = false;
            $this->data['result']['headings']['error']['duplicate'] = null;
        }
    }

    /**
     * Grades title keywords on page
     *
     * @param $bodyKeyWords
     * @param $requestReportStats
     * @return void
     */
    private function runTitleKeywords($bodyKeyWords, $requestReportStats): void
    {
        $titleKeywords = array_filter(explode(' ', preg_replace('/[^\w]/ui', ' ', mb_strtolower($this->title))));

        $this->data['result']['content_keywords'] = [
            'passed' => true,
            'importance' => 'high',
            'value' => array_intersect($titleKeywords, $bodyKeyWords)
        ];

        if (!array_intersect($titleKeywords, $bodyKeyWords)) {
            $this->data['result']['content_keywords']['passed'] = false;
            $this->data['result']['content_keywords']['error']['missing'] = $titleKeywords;
        }

        $this->data['result']['content_length'] = [
            'passed' => true,
            'importance' => 'low',
            'value' => count($bodyKeyWords)
        ];

        if (count($bodyKeyWords) < config('webaseo.settings.report_limit_min_words')) {
            $this->data['result']['content_length']['passed'] = false;
            $this->data['result']['content_length']['error']['too_few'] = ['min' => config('webaseo.settings.report_limit_min_words')];
        }

        $this->data['result']['seo_friendly_url'] = [
            'passed' => true,
            'importance' => 'high',
            'value' => $requestReportStats['url']
        ];

        if (preg_match('/[\?\=\_\%\,\ ]/ui', $requestReportStats['url'])) {
            $this->data['result']['seo_friendly_url']['passed'] = false;
            $this->data['result']['seo_friendly_url']['error']['bad_format'] = null;
        }

        if (empty(array_filter($titleKeywords, function ($keyword) { if (strpos(mb_strtolower($this->url), mb_strtolower($keyword)) !== false) { return true; } return false; }))) {
            $this->data['result']['seo_friendly_url']['passed'] = false;
            $this->data['result']['seo_friendly_url']['error']['missing'] = null;
        }
    }

    /**
     * Grades usage of alt attribute on images
     *
     * @param $domDocument
     * @return void
     */
    private function runImageAlts($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('img') as $node) {
            if (!empty($node->getAttribute('src'))) {
                if (empty($node->getAttribute('alt'))) {
                    $this->imageAlts[] = [
                        'url' => $this->moldUrl($node->getAttribute('src')),
                        'text' => $this->moldText($node->getAttribute('alt'))
                    ];
                }
            }
        }

        $this->data['result']['image_keywords'] = [
            'passed' => true,
            'importance' => 'high',
            'value' => null
        ];

        if (count($this->imageAlts) > 0) {
            $this->data['result']['image_keywords']['passed'] = false;
            $this->data['result']['image_keywords']['error']['missing'] = $this->imageAlts;
        }
    }

    /**
     * Grade links found on page
     *
     * @param $domDocument
     * @return void
     */
    private function runPageLinks($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('a') as $node) {
            if (!empty($node->getAttribute('href')) && mb_substr($node->getAttribute('href'), 0, 1) != '#') {
                if ($this->isInternalUrl($this->moldUrl($node->getAttribute('href')))) {
                    $this->pageLinks['Internals'][] = [
                        'url' => $this->moldUrl($node->getAttribute('href')),
                        'text' => $this->moldText($node->textContent),
                    ];
                } else {
                    $this->pageLinks['Externals'][] = [
                        'url' => $this->moldUrl($node->getAttribute('href')),
                        'text' => $this->moldText($node->textContent),
                    ];
                }
            }
        }

        $this->data['result']['in_page_links'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => $this->pageLinks
        ];

        if (array_sum(array_map('count', $this->pageLinks)) > config('webaseo.settings.report_limit_max_links')) {
            $this->data['result']['in_page_links']['passed'] = false;
            $this->data['result']['in_page_links']['error']['too_many'] = ['max' => config('webaseo.settings.report_limit_max_links')];
        }
    }

    /**
     * Grades encryption used
     *
     * @param $requestReportStats
     * @return void
     */
    private function runHttpEncryption($requestReportStats): void
    {
        $httpScheme = parse_url($this->url, PHP_URL_SCHEME);

        $this->data['result']['https_encryption'] = [
            'passed' => true,
            'importance' => 'high',
            'value' => $requestReportStats['url']
        ];

        if (strtolower($httpScheme) != 'https') {
            $this->data['result']['https_encryption']['passed'] = false;
            $this->data['result']['https_encryption']['error']['missing'] = 'https';
        }
    }

    /**
     * Grades 404 page
     *
     * @return void
     * @throws GuzzleException
     */
    private function run404Page(): void
    {
        if (!isset($this->cachedNotFoundPage)) {
            $notFoundPage = false;
            $notFoundUrl = parse_url($this->url, PHP_URL_SCHEME) . '://' . parse_url($this->url, PHP_URL_HOST) . '/404-' . md5(uniqid());
            try {
                (new HttpClient())->get($notFoundUrl, [
                    'proxy' => [
                        'http' => $this->getRequestProxy(),
                        'https' => $this->getRequestProxy()
                    ],
                    'timeout' => config('webaseo.settings.request_timeout'),
                    'headers' => [
                        'User-Agent' => config('webaseo.settings.request_user_agent')
                    ]
                ]);
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    if ($e->getResponse()->getStatusCode() == '404') {
                        $notFoundPage = $notFoundUrl;
                    }
                }
            }

            $this->cachedNotFoundPage = $notFoundPage;
        } else {
            $notFoundPage = $this->cachedNotFoundPage;
        }

        $this->data['result']['404_page'] = [
            'passed' => true,
            'importance' => 'high',
            'value' => $notFoundPage
        ];

        if (!$notFoundPage) {
            $this->data['result']['404_page']['passed'] = false;
            $this->data['result']['404_page']['error']['missing'] = null;
        }
    }

    /**
     * Grade usage of robots.txt on site
     *
     * @return void
     */
    private function runRobots(): void
    {
        if (!isset($this->cachedRobotsRequest)) {
            $robotsUrl = parse_url($this->url, PHP_URL_SCHEME) . '://' . parse_url($this->url, PHP_URL_HOST) . '/robots.txt';

            try {
                $httpClient = new HttpClient();
                $this->cachedRobotsRequest = $httpClient->get($robotsUrl, [
                    'proxy' => [
                        'http' => $this->getRequestProxy(),
                        'https' => $this->getRequestProxy()
                    ],
                    'timeout' => config('webaseo.settings.request_timeout'),
                    'headers' => [
                        'User-Agent' => config('webaseo.settings.request_user_agent')
                    ]
                ]);
            } catch (GuzzleException $e) {
            }

        }
        $robotRequest = $this->cachedRobotsRequest;

        if ($robotRequest) {
            $robotRules = preg_split('/\n|\r/', $robotRequest->getBody()->getContents(), -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $robotRules = [];
        }

        foreach ($robotRules as $robotRule) {
            $rule = explode(':', $robotRule, 2);

            $directive = trim(strtolower($rule[0] ?? null));
            $value = trim($rule[1] ?? null);

            if ($directive == 'disallow' && $value) {
                if (preg_match($this->formatRobotsRule($value), $this->url)) {
                    $this->robotsRulesFailed[] = $value;
                    $this->robots = false;
                }
            }

            if ($directive == 'sitemap') {
                if ($value) {
                    $this->sitemaps[] = $value;
                }
            }
        }

        $this->data['result']['robots'] = [
            'passed' => true,
            'importance' => 'high',
            'value' => null
        ];

        if (!$this->robots) {
            $this->data['result']['robots']['passed'] = false;
            $this->data['result']['robots']['error']['failed'] = $this->robotsRulesFailed;
        }

        $this->data['result']['sitemap'] = [
            'passed' => true,
            'importance' => 'low',
            'value' => $this->sitemaps
        ];

        if (empty($this->sitemaps)) {
            $this->data['result']['sitemap']['passed'] = false;
            $this->data['result']['sitemap']['error']['failed'] = null;
        }
    }

    /**
     * Grade noindex
     *
     * @param $domDocument
     * @return void
     */
    private function runNoIndex($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if (strtolower($node->getAttribute('name')) == 'robots' || strtolower($node->getAttribute('name')) == 'googlebot') {
                    if (preg_match('/\bnoindex\b/', $node->getAttribute('content'))) {
                        $this->noIndex = $node->getAttribute('content');
                    }
                }
            }
        }

        $this->data['result']['noindex'] = [
            'passed' => true,
            'importance' => 'high',
            'value' => $this->noIndex
        ];

        if ($this->noIndex) {
            $this->data['result']['noindex']['passed'] = false;
            $this->data['result']['noindex']['error']['missing'] = null;
        }
    }

    /**
     * Grades inclusion of language on page
     *
     * @param $domDocument
     * @return void
     */
    private function runLanguage($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('html') as $node) {
            if ($node->getAttribute('lang')) {
                $this->language = $node->getAttribute('lang');
            }
        }

        $this->data['result']['language'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => $this->language
        ];

        if (!$this->language) {
            $this->data['result']['language']['passed'] = false;
            $this->data['result']['language']['error']['missing'] = null;
        }
    }

    /**
     * Grades usage of favicon on page
     *
     * @param $domDocument
     * @return void
     */
    private function runFavIcon($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('link') as $node) {
                if (preg_match('/\bicon\b/i', $node->getAttribute('rel'))) {
                    $this->favicon = $this->moldUrl($node->getAttribute('href'));
                }
            }
        }

        $this->data['result']['favicon'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => $this->favicon
        ];

        if (!$this->favicon) {
            $this->data['result']['favicon']['passed'] = false;
            $this->data['result']['favicon']['error']['missing'] = null;
        }
    }

    /**
     * Grades mixed content on page
     *
     * @param $domDocument
     * @return void
     */
    private function runMixedContent($domDocument): void
    {
        if (str_starts_with($this->url, 'https://')) {
            foreach ($domDocument->getElementsByTagName('script') as $node) {
                // If the script has a source
                if ($node->getAttribute('src') && str_starts_with($node->getAttribute('src'), 'http://')) {
                    $this->mixedContent['JavaScripts'][] = $this->moldUrl($node->getAttribute('src'));
                }
            }
            foreach ($domDocument->getElementsByTagName('link') as $node) {
                if (preg_match('/\bstylesheet\b/', $node->getAttribute('rel')) && str_starts_with($node->getAttribute('href'), 'http://')) {
                    $this->mixedContent['CSS'][] = $this->moldUrl($node->getAttribute('href'));
                }
            }
            foreach ($domDocument->getElementsByTagName('img') as $node) {
                if (!empty($node->getAttribute('src')) && str_starts_with($node->getAttribute('src'), 'http://')) {
                    $this->mixedContent['Images'][] = $this->moldUrl($node->getAttribute('src'));
                }
            }
            foreach ($domDocument->getElementsByTagName('source') as $node) {
                if (!empty($node->getAttribute('src')) && str_starts_with($node->getAttribute('type'), 'audio/') && str_starts_with($node->getAttribute('src'), 'http://')) {
                    $this->mixedContent['Audios'][] = $this->moldUrl($node->getAttribute('src'));
                }
            }
            foreach ($domDocument->getElementsByTagName('source') as $node) {
                if (!empty($node->getAttribute('src')) && str_starts_with($node->getAttribute('type'), 'video/') && str_starts_with($node->getAttribute('src'), 'http://')) {
                    $this->mixedContent['Videos'][] = $this->moldUrl($node->getAttribute('src'));
                }
            }
            foreach ($domDocument->getElementsByTagName('iframe') as $node) {
                if (!empty($node->getAttribute('src')) && str_starts_with($node->getAttribute('src'), 'http://')) {
                    $this->mixedContent['Iframes'][] = $this->moldUrl($node->getAttribute('src'));
                }
            }
        }

        $this->data['result']['mixed_content'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => null
        ];

        if (!empty($this->mixedContent)) {
            $this->data['result']['mixed_content']['passed'] = false;
            $this->data['result']['mixed_content']['error']['failed'] = $this->mixedContent;
        }
    }

    /**
     * Grades cross origin requests on page
     *
     * @param $domDocument
     * @return void
     */
    private function runCrossOrigins($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('a') as $node) {
            if (!$this->isInternalUrl($this->moldUrl($node->getAttribute('href')))) {
                if ($node->getAttribute('target') == '_blank') {
                    if (!str_contains(strtolower($node->getAttribute('rel')), 'noopener') && !str_contains(strtolower($node->getAttribute('rel')), 'nofollow')) {
                        $this->unsafeCrossOriginLinks[] = $this->moldUrl($node->getAttribute('href'));
                    }
                }
            }
        }

        $this->data['result']['unsafe_cross_origin_links'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => null
        ];

        if (count($this->unsafeCrossOriginLinks) > 0) {
            $this->data['result']['unsafe_cross_origin_links']['passed'] = false;
            $this->data['result']['unsafe_cross_origin_links']['error']['failed'] = $this->unsafeCrossOriginLinks;
        }
    }

    /**
     * Grades usage of plain text emails on page
     *
     * @param $reportResponse
     * @return void
     */
    private function runPlainTextEmails($reportResponse): void
    {
        preg_match_all('/([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+)/i', $reportResponse, $this->plainTextEmails, PREG_UNMATCHED_AS_NULL);

        if (isset($this->plainTextEmails[0])) {
            $this->plainTextEmails[0] = array_filter($this->plainTextEmails[0], function ($email) { return filter_var($email, FILTER_VALIDATE_EMAIL); });
        }

        $this->data['result']['plaintext_email'] = [
            'passed' => true,
            'importance' => 'low',
            'value' => null
        ];

        if (isset($this->plaintextEmails[0]) && !empty($this->plaintextEmails[0])) {
            $this->data['result']['plaintext_email']['passed'] = false;
            $this->data['result']['plaintext_email']['error']['failed'] = $this->plaintextEmails[0];
        }
    }

    /**
     * Grades http requests made on page
     *
     * @param $domDocument
     * @return void
     */
    private function runHttpRequests($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('script') as $node) {
            if ($node->getAttribute('src')) {
                $this->httpRequests['JavaScripts'][] = $this->moldUrl($node->getAttribute('src'));
            }
        }

        foreach ($domDocument->getElementsByTagName('link') as $node) {
            if (preg_match('/\bstylesheet\b/', $node->getAttribute('rel'))) {
                $this->httpRequests['CSS'][] = $this->moldUrl($node->getAttribute('href'));
            }
        }

        foreach ($domDocument->getElementsByTagName('img') as $node) {
            if (!empty($node->getAttribute('src'))) {
                if (!preg_match('/\blazy\b/', $node->getAttribute('loading')) && $node->getAttribute('src')) {
                    $this->httpRequests['Images'][] = $this->moldUrl($node->getAttribute('src'));
                }
            }
        }

        foreach ($domDocument->getElementsByTagName('audio') as $audioNode) {
            if ($audioNode->getAttribute('preload') != 'none') {
                foreach ($audioNode->getElementsByTagName('source') as $node) {
                    if (!empty($node->getAttribute('src')) && str_starts_with($node->getAttribute('type'), 'audio/')) {
                        $this->httpRequests['Audios'][] = $this->moldUrl($node->getAttribute('src'));
                    }
                }
            }
        }

        foreach ($domDocument->getElementsByTagName('video') as $videoNode) {
            if ($videoNode->getAttribute('preload') != 'none') {
                foreach ($videoNode->getElementsByTagName('source') as $node) {
                    if (!empty($node->getAttribute('src')) && str_starts_with($node->getAttribute('type'), 'video/')) {
                        $this->httpRequests['Videos'][] = $this->moldUrl($node->getAttribute('src'));
                    }
                }
            }
        }

        foreach ($domDocument->getElementsByTagName('iframe') as $node) {
            if (!empty($node->getAttribute('src'))) {
                if (!preg_match('/\blazy\b/', $node->getAttribute('loading')) && $node->getAttribute('src')) {
                    $this->httpRequests['Iframes'][] = $this->moldUrl($node->getAttribute('src'));
                }
            }
        }

        $this->data['result']['http_requests'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => $this->httpRequests
        ];

        if (array_sum(array_map('count', $this->httpRequests)) > config('webaseo.settings.report_limit_http_requests')) {
            $this->data['result']['http_requests']['passed'] = false;
            $this->data['result']['http_requests']['error']['too_many'] = ['max' => config('webaseo.settings.report_limit_http_requests')];
        }
    }

    /**
     * Grades image formats used on page
     *
     * @param $domDocument
     * @param $reportResponse
     * @return void
     */
    private function runImageFormats($domDocument, $reportResponse): void
    {
        foreach ($domDocument->getElementsByTagName('img') as $node) {
            if (!empty($node->getAttribute('src'))) {
                if (!in_array(mb_strtolower(pathinfo($this->url, PATHINFO_EXTENSION)), array_map('strtolower', preg_split('/\n|\r/', config('webaseo.settings.report_limit_image_formats'), -1, PREG_SPLIT_NO_EMPTY))) && mb_strtolower(pathinfo($this->moldUrl($node->getAttribute('src')), PATHINFO_EXTENSION)) != 'svg') {
                    $search = '\/';
                    foreach (preg_split('/,/', config('webaseo.settings.report_limit_image_formats'), -1, PREG_SPLIT_NO_EMPTY) as $format) {
                        $search .= preg_quote(pathinfo($node->getAttribute('src'), PATHINFO_FILENAME)) . '\.' . strtolower(preg_quote($format)) . '\"|\/';
                    }
                    if (!preg_match('/' . mb_substr($search, 0, -3) . '/', $reportResponse)) {
                        $this->imageFormats[] = [
                            'url' => $this->moldUrl($node->getAttribute('src')),
                            'text' => $this->moldText($node->getAttribute('alt'))
                        ];
                    }
                }
            }
        }

        $this->data['result']['image_format'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => preg_split('/\n|\r/', config('webaseo.settings.report_limit_image_formats'), -1, PREG_SPLIT_NO_EMPTY)
        ];

        if (count($this->imageFormats) > 0) {
            $this->data['result']['image_format']['passed'] = false;
            $this->data['result']['image_format']['error']['bad_format'] = $this->imageFormats;
        }
    }

    /**
     * Grades usage of defer in JS scripts on page
     *
     * @param $domDocument
     * @return void
     */
    private function runDeferJavaScript($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('script') as $node) {
            if ($node->getAttribute('src') && !$node->hasAttribute('defer')) {
                $this->deferJavaScript[] = $this->moldUrl($node->getAttribute('src'));
            }
        }

        $this->data['result']['defer_javascript'] = [
            'passed' => true,
            'importance' => 'low',
            'value' => null
        ];

        if (count($this->deferJavaScript) > 0) {
            $this->data['result']['defer_javascript']['passed'] = false;
            $this->data['result']['defer_javascript']['error']['missing'] = $this->deferJavaScript;
        }
    }

    /**
     * Grades DOM size
     *
     * @param $domDocument
     * @return void
     */
    private function runDomSize($domDocument): void
    {
        $domNodesCount = count($domDocument->getElementsByTagName('*'));

        $this->data['result']['dom_size'] = [
            'passed' => true,
            'importance' => 'low',
            'value' => $domNodesCount
        ];

        if ($domNodesCount > config('webaseo.settings.report_limit_max_dom_nodes')) {
            $this->data['result']['dom_size']['passed'] = false;
            $this->data['result']['dom_size']['error']['too_many'] = ['max' => config('webaseo.settings.report_limit_max_dom_nodes')];
        }
    }

    /**
     * Grades data structure
     *
     * @param $domDocument
     * @return void
     */
    private function runStructuredData($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if (preg_match('/\bog:\b/', $node->getAttribute('property')) && $node->getAttribute('content')) {
                    $this->structuredData['Open Graph'][$node->getAttribute('property')] = $this->moldText($node->getAttribute('content'));
                }

                if (preg_match('/\btwitter:\b/', $node->getAttribute('name')) && $node->getAttribute('content')) {
                    $this->structuredData['Twitter'][$node->getAttribute('name')] = $this->moldText($node->getAttribute('content'));
                }
            }

            foreach ($domDocument->getElementsByTagName('script') as $node) {
                if (strtolower($node->getAttribute('type')) == 'application/ld+json') {
                    $this->data = json_decode($node->nodeValue, true);

                    if (isset($this->data['@context']) && is_string($this->data['@context']) && in_array(mb_strtolower($this->data['@context']), ['https://schema.org', 'http://schema.org'])) {
                        $this->structuredData['Schema.org'] = $this->data;
                    }
                }
            }
        }

        $this->data['result']['structured_data'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => $this->structuredData
        ];

        if (empty($this->structuredData)) {
            $this->data['result']['structured_data']['passed'] = false;
            $this->data['result']['structured_data']['error']['missing'] = null;
        }
    }

    /**
     * Grades meta viewport
     *
     * @param $domDocument
     * @return void
     */
    private function runMetaViewport($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if (strtolower($node->getAttribute('name')) == 'viewport') {
                    $this->metaViewport = $this->moldText($node->getAttribute('content'));
                }
            }
        }

        $this->data['result']['meta_viewport'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => $this->metaViewport
        ];

        if (!$this->metaViewport) {
            $this->data['result']['meta_viewport']['passed'] = false;
            $this->data['result']['meta_viewport']['error']['missing'] = null;
        }
    }

    /**
     * Grades use of charset in page
     *
     * @param $domDocument
     * @return void
     */
    private function runCharset($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('head') as $headNode) {
            foreach ($headNode->getElementsByTagName('meta') as $node) {
                if ($node->getAttribute('charset')) {
                    $this->charset = $this->moldText($node->getAttribute('charset'));
                }
            }
        }

        $this->data['result']['charset'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => $this->charset
        ];

        if (!$this->charset) {
            $this->data['result']['charset']['passed'] = false;
            $this->data['result']['charset']['error']['missing'] = null;
        }
    }

    /**
     * Grades HTML to text ratio
     *
     * @return void
     */
    private function runTextRatio(): void
    {
        $textRatio = round(((!empty($reportResponse) && !empty($pageText)) ? (mb_strlen($pageText) / mb_strlen($reportResponse) * 100) : 0));

        $this->data['result']['text_html_ratio'] = [
            'passed' => true,
            'importance' => 'low',
            'value' => $textRatio
        ];

        if ($textRatio < config('webaseo.settings.report_limit_min_text_ratio')) {
            $this->data['result']['text_html_ratio']['passed'] = false;
            $this->data['result']['text_html_ratio']['error']['too_small'] = ['min' => config('webaseo.settings.report_limit_min_text_ratio')];
        }
    }

    /**
     * Grades use of deprecated HTML tags on page
     *
     * @param $domDocument
     * @return void
     */
    private function runDeprecatedHtmlTags($domDocument): void
    {
        foreach (preg_split('/,/', config('webaseo.settings.report_limit_deprecated_html_tags'), -1, PREG_SPLIT_NO_EMPTY) as $tagName) {
            foreach ($domDocument->getElementsByTagName($tagName) as $node) {
                if (isset($this->deprecatedHtmlTags[$node->nodeName])) {
                    $this->deprecatedHtmlTags[$node->nodeName] += 1;
                } else {
                    $this->deprecatedHtmlTags[$node->nodeName] = 1;
                }
            }
        }

        $this->data['result']['deprecated_html_tags'] = [
            'passed' => true,
            'importance' => 'low',
            'value' => null
        ];

        if (count($this->deprecatedHtmlTags) > 1) {
            $this->data['result']['deprecated_html_tags']['passed'] = false;
            $this->data['result']['deprecated_html_tags']['error']['bad_tags'] = $this->deprecatedHtmlTags;
        }
    }

    /**
     * Grades inclusion of social links in page
     *
     * @param $domDocument
     * @return void
     */
    private function runSocial($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('a') as $node) {
            if (!empty($node->getAttribute('href')) && mb_substr($node->getAttribute('href'), 0, 1) != '#') {
                if (!$this->isInternalUrl($this->moldUrl($node->getAttribute('href')))) {
                    $socials = ['twitter.com' => 'Twitter', 'www.twitter.com' => 'Twitter', 'www.x.com' => 'X', 'facebook.com' => 'Facebook', 'www.facebook.com' => 'Facebook', 'instagram.com' => 'Instagram', 'www.instagram.com' => 'Instagram', 'youtube.com' => 'YouTube', 'www.youtube.com' => 'YouTube', 'linkedin.com' => 'LinkedIn', 'www.linkedin.com' => 'LinkedIn'];

                    $host = parse_url($this->moldUrl($node->getAttribute('href')), PHP_URL_HOST);

                    if (!empty($host) && array_key_exists($host, $socials)) {
                        $this->social[$socials[$host]][] = [
                            'url' => $this->moldUrl($node->getAttribute('href')),
                            'text' => $this->moldText($node->textContent),
                        ];
                    }
                }
            }
        }

        $this->data['result']['social'] = [
            'passed' => true,
            'importance' => 'low',
            'value' => $this->social
        ];

        if (empty($this->social)) {
            $this->data['result']['social']['passed'] = false;
            $this->data['result']['social']['error']['missing'] = null;
        }
    }

    /**
     * Grades use on inline CSS on page
     *
     * @param $domDocument
     * @return void
     */
    private function runInlineCss($domDocument): void
    {
        foreach ($domDocument->getElementsByTagName('*') as $node) {
            if ($node->nodeName != 'svg' && !empty($node->getAttribute('style'))) {
                $this->inlineCss[] = $node->getAttribute('style');
            }
        }

        $this->data['result']['inline_css'] = [
            'passed' => true,
            'importance' => 'low',
            'value' => null
        ];

        if (count($this->inlineCss) > 1) {
            $this->data['result']['inline_css']['passed'] = false;
            $this->data['result']['inline_css']['error']['failed'] = $this->inlineCss;
        }
    }

    /**
     * Grades general performance of page
     *
     * @param $requestReportStats
     * @param $requestReport
     * @return void
     */
    private function runPerformance($requestReportStats, $requestReport): void
    {
        $this->data['result']['load_time'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => $requestReportStats['total_time']
        ];

        if ($requestReportStats['total_time'] > config('webaseo.settings.report_limit_load_time')) {
            $this->data['result']['load_time']['passed'] = false;
            $this->data['result']['load_time']['error']['too_slow'] = ['max' => config('webaseo.settings.report_limit_load_time')];
        }

        $this->data['result']['text_compression'] = [
            'passed' => true,
            'importance' => 'high',
            'value' => $requestReportStats['size_download'],
        ];

        if (!in_array('gzip', $requestReport->getHeader('x-encoded-content-encoding'))) {
            $this->data['result']['text_compression']['passed'] = false;
            $this->data['result']['text_compression']['error']['missing'] = null;
        }
    }

    /**
     * Grades general security of page
     *
     * @param $requestReport
     * @return void
     */
    private function runSecurity($requestReport): void
    {
        $this->data['result']['server_signature'] = [
            'passed' => true,
            'importance' => 'medium',
            'value' => $requestReport->getHeader('server'),
        ];

        if (!empty($requestReport->getHeader('server'))) {
            $this->data['result']['server_signature']['passed'] = false;
            $this->data['result']['server_signature']['error']['failed'] = null;
        }
    }
}