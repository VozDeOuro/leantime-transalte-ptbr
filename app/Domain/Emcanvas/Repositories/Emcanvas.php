<?php

/**
 * Repository
 */

namespace Leantime\Domain\Emcanvas\Repositories {

    class Emcanvas extends \Leantime\Domain\Canvas\Repositories\Canvas
    {
        /**
         * Constant that must be redefined
         */
        protected const CANVAS_NAME = 'em';

        /***
         * icon - Icon associated with canvas (must be extended)
         *
         * @access public
         * @var    string Fontawesome icone
         */
        protected string $icon = 'fa-heart';

        /***
         * disclaimer - Disclaimer
         *
         * @access protected
         * @var    string Disclaimer (including href)
         */
        protected string $disclaimer = 'text.em.disclaimer';

        /**
         * canvasTypes - Must be extended
         *
         * @acces protected
         * @var   array
         */
        protected array $canvasTypes = [
            'em_who'     => ['icon' => 'fa-1', 'title' => 'box.em.who'],
            'em_what'    => ['icon' => 'fa-2', 'title' => 'box.em.what'],
            'em_see'     => ['icon' => 'fa-3', 'title' => 'box.em.see'],
            'em_say'     => ['icon' => 'fa-4', 'title' => 'box.em.say'],
            'em_do'      => ['icon' => 'fa-5', 'title' => 'box.em.do'],
            'em_hear'    => ['icon' => 'fa-6', 'title' => 'box.em.hear'],
            'em_pains'   => ['icon' => 'fa-face-frown', 'title' => 'box.em.pains'],
            'em_gains'   => ['icon' => 'fa-face-smile', 'title' => 'box.em.gains'],
            'em_motives' => ['icon' => 'fa-face-rolling-eyes', 'title' => 'box.em.motives'],
        ];

        /**
         * dataLabels - Data labels (may be extended)
         *
         * @acces protected
         * @var   array
         */
        protected array $dataLabels = [
        1 => ['title' => 'label.em.description', 'field' => 'conclusion',  'active' => true],
                                        2 => ['title' => 'label.data',           'field' => 'data',        'active' => false],
                                        3 => ['title' => 'label.conclusion',     'field' => 'assumptions', 'active' => false],
                                        ];

        /**
         * relatesLabels - Relates to label (same structure as `statusLabels`)
         *
         * @acces public
         * @var   array
         */
        protected array $relatesLabels = [];
    }
}
