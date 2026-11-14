<?php
namespace App\Http\Controllers;
class HelpController extends Controller
{
    public function index()
    {
        $sections = json_decode(file_get_contents(resource_path('manual/sections.json')), true, 512, JSON_THROW_ON_ERROR);
        return response()->view('help.index', compact('sections'))->header('Cache-Control', 'no-store, private');
    }
    public function download()
    {
        return response()->download(resource_path('manual/manual-bacuri-agenda.html'), 'manual-bacuri-agenda-v21.html', ['Cache-Control'=>'no-store, private','X-Content-Type-Options'=>'nosniff']);
    }
}
