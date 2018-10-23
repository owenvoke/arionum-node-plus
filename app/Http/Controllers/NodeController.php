<?php

namespace App\Http\Controllers;

use App\Block;

/**
 * Class NodeController
 */
final class NodeController
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $data = [
            'currentBlockHeight' => Block::current()->height,
            'isPublicNode'       => config('arionum.app.public_api') ? 'yes' : 'no',
        ];

        return view('index', $data);
    }
}
