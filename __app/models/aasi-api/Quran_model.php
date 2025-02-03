<?php defined('BASEPATH') or exit('No direct script access allowed');

class Quran_model extends CI_Model
{
	function __construct()
	{
		parent::__construct();
		$this->load->library(['quran']);
	}

    function complete($request)
    {
        $from = $request->params->from ?? 1;
        $to = $request->params->to ?? 1;

        $chapters = [];
        for ($i=$from; $i <= $to; $i++) { 
            $chapters[] = $this->quran->fetchChapter($i)->chapter;
        }

        foreach ($chapters as $key => $value) {
            // Convert to indonesian language
            if ($chapters[$key]->revelation_place == 'makkah') {
                $chapters[$key]->revelation_place = 'Mekah';
            } 
            if ($chapters[$key]->revelation_place == 'madinah') {
                $chapters[$key]->revelation_place = 'Madinah';
            }

            // Inject surah description
            // $chapters[$key]->description = $chapters2[$key]->deskripsi;

            // Get Ayah Detail
            $chapterNo = $chapters[$key]->id;
            $ayah = $this->quran->fetchAyah($chapterNo);
            $chapters[$key]->ayah = $ayah->ayat;
        }

        return [TRUE, ['result' => $chapters]];
    }

    function chapters()
    {
        $chapters = $this->quran->fetchChapters()->chapters;
        // $chapters2 = $this->quran->fetchChapters2();

        foreach ($chapters as $key => $value) {
            if ($chapters[$key]->revelation_place == 'makkah') {
                $chapters[$key]->revelation_place = 'Mekah';
            } 
            if ($chapters[$key]->revelation_place == 'madinah') {
                $chapters[$key]->revelation_place = 'Madinah';
            }
            // $chapters[$key]->description = $chapters2[$key]->deskripsi;
        }

        return [TRUE, ['result' => $chapters]];
    }
}