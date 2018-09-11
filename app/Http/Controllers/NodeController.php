<?php

namespace App\Http\Controllers;

use App\Block;

/**
 * Class NodeController
 */
final class NodeController
{
    /**
     *
     */
    public function index()
    {
        $data = [
            'currentBlockHeight' => Block::current()->height,
            'isPublicNode'       => config('node.public_api') ? 'yes' : 'no',
        ];

        return view('index', $data);
    }
}
