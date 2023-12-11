<?php

namespace Osit\Webaseo\Controllers;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Osit\Webaseo\Traits\ReportHelper;


/**
* Webaseo - main class
*
* Webaseo
* distributed under the MIT License
*
* @author  Dominic Moeketsi developer@osit.co.za
* @company OmniSol Information Technology (PTY) LTD
* @version 1.0.0
*/
class WebaseoController extends Controller
{
    use ReportHelper;

    private $data = [];
    private $report = [];

    /**
     * The cached Not Found Page result.
     *
     * @var
     */
    private $cachedNotFoundPage;

    /**
     * The cached Robots Request result.
     *
     * @var
     */
    private $cachedRobotsRequest;

    private $requestErrorMessage = "";

    private $categories = [
        'seo' => ['title', 'meta_description', 'headings', 'content_keywords', 'image_keywords', 'seo_friendly_url', '404_page', 'robots', 'noindex', 'in_page_links', 'language', 'favicon'],
        'performance' => ['text_compression', 'load_time', 'page_size', 'http_requests', 'image_format', 'defer_javascript', 'dom_size'],
        'security' => ['https_encryption', 'mixed_content', 'server_signature', 'unsafe_cross_origin_links', 'plaintext_email'],
        'miscellaneous' => ['structured_data', 'meta_viewport', 'charset', 'sitemap', 'social', 'content_length', 'text_html_ratio', 'inline_css', 'deprecated_html_tags']
    ];


    private $url = "";
    private $title = null;
    private $metaDescription = null;
    private $headings = [];
    private $imageAlts = [];
    private $pageLinks = [];
    private $sitemaps = [];
    private $robotsRulesFailed = [];
    private $robots = true;
    private $noIndex = null;
    private $language = null;
    private $favicon = null;
    private $mixedContent = [];
    private $unsafeCrossOriginLinks = [];
    private $plainTextEmails = [];
    private $httpRequests = [];
    private $imageFormats = [];
    private $deferJavaScript = [];
    private $structuredData = [];
    private $metaViewport = null;
    private $charset = null;
    private $deprecatedHtmlTags = [];
    private $social = [];
    private $inlineCss = [];


    use AuthorizesRequests, ValidatesRequests;

    public function __construct()
    {
        // Uncomment below line to enable auth for this controller
        // $this->middleware('auth');
    }

    /**
     * Generate SEO report for your website
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Foundation\Application
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function run(Request $request): \Illuminate\Foundation\Application|\Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        $request["url"] = $request->host();
        $this->validateRequestUrl($request);

        $report = $this->buildReport($request->requestReport, $request->requestReportTransferStats);

        return view("webaseo::report", [
            "categories" => $this->categories,
            "result" => $report["report"]["results"] ?? null,
            "score" => $report["report"]["overallResult"] ?? null,
            "website" => $report["report"]["results"]["seo_friendly_url"]["value"] ?? null,
            "error" => $report["error"],
            "requestErrorMessage" => $this->requestErrorMessage
        ]);
    }
}