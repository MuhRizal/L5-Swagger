<?php

namespace L5Swagger\Http\Controllers;

use File;
use Request;
use Response;
use L5Swagger\Generator;
use Illuminate\Routing\Controller as BaseController;

class SwaggerController extends BaseController
{
    /**
     * Dump api-docs.json content endpoint.
     *
     * @param string $jsonFile
     *
     * @return \Response
     */
    public function docs($jsonFile = null)
    {
		if (config('l5-swagger.api.separated_doc')) {
			
            $group = explode('.', Request::route()->getName())[1];
            $filePath = config('l5-swagger.doc_groups.'.$group.'.paths.docs', config('l5-swagger.paths.docs')) . '/' .
                (!is_null($jsonFile) ? $jsonFile : config(
                    'l5-swagger.doc_groups.'.$group.'.paths.docs_json',
                    $group.'-'.config('l5-swagger.doc_groups.'.$group.'.paths.docs_json', 'api-docs.json')
                ));
        } else {
            $filePath = config('l5-swagger.paths.docs') . '/' .
                (!is_null($jsonFile) ? $jsonFile : config('l5-swagger.paths.docs_json', 'api-docs.json'));
        }
		
        if (! File::exists($filePath)) {
            abort(404, 'Cannot find '.$filePath);
        }

        $content = File::get($filePath);

        return Response::make($content, 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Display Swagger API page.
     *
     * @return \Response
     */
    public function api()
    {
        if (config('l5-swagger.generate_always')) {
            Generator::generateDocs();
        }

        if ($proxy = config('l5-swagger.proxy')) {
            if (! is_array($proxy)) {
                $proxy = [$proxy];
            }
            Request::setTrustedProxies($proxy, \Illuminate\Http\Request::HEADER_X_FORWARDED_ALL);
        }

		$docsRoute = 'l5-swagger.docs';
        $docsJson = config('l5-swagger.paths.docs_json', 'api-docs.json');
        $sort = config('l5-swagger.operations_sort');
        $additionalConfigUrl = config('l5-swagger.additional_config_url');
        $validatorUrl = config('l5-swagger.validator_url');
        $title = config('l5-swagger.api.title');
        $oauth2CallbackRoute = 'l5-swagger.routes.oauth2_callback';
		
		if (config('l5-swagger.api.separated_doc')) {
			$group = explode('.', Request::route()->getName())[1];
            $docsRoute = 'l5-swagger.'.$group.'.docs';
            $docsJson = config('l5-swagger.doc_groups.'.$group.'.paths.docs_json', $docsJson);
            $sort = config('l5-swagger.doc_groups.'.$group.'.operations_sort', $sort);
            $additionalConfigUrl = config('l5-swagger.doc_groups.'.$group.'.additional_config_url', $additionalConfigUrl);
            $validatorUrl = config('l5-swagger.doc_groups.'.$group.'.validator_url', $validatorUrl);
            $title = config('l5-swagger.doc_groups.'.$group.'.api.title', $group.' - '.$title);
            $oauth2CallbackRoute = 'l5-swagger.'.$group.'.oauth2_callback';
		
			$response = Response::make(
				view('l5-swagger::index', [
					'secure' => Request::secure(),
					'urlToDocs' => route($docsRoute, $docsJson),
					'operationsSorter' => $sort,
					'configUrl' => $additionalConfigUrl,
					'validatorUrl' => $validatorUrl,
					'title' => $title,
					'oauth2_callback' => route($oauth2CallbackRoute),
				]),
				200
			);
		} else {
			// Need the / at the end to avoid CORS errors on Homestead systems.
			$response = Response::make(
				view('l5-swagger::index', [
					'secure' => Request::secure(),
					'urlToDocs' => route('l5-swagger.docs', config('l5-swagger.paths.docs_json', 'api-docs.json')),
					'operationsSorter' => config('l5-swagger.operations_sort'),
					'configUrl' => config('l5-swagger.additional_config_url'),
					'validatorUrl' => config('l5-swagger.validator_url'),
				]),
				200
			);
		}

        return $response;
    }

    /**
     * Display Oauth2 callback pages.
     *
     * @return string
     */
    public function oauth2Callback()
    {
        return \File::get(swagger_ui_dist_path('oauth2-redirect.html'));
    }
}
