<?php namespace PolloZen\MostVisited\Components;

use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Carbon\Carbon;
use PolloZen\MostVisited\Models\Visits;
use RainLab\Blog\Models\Category;
use RainLab\Blog\Models\Post;

class TopVisited extends ComponentBase
{
     /**
     * @var Illuminate\Database\Eloquent\Collection | array
     */
    public $topPosts;

    /**
     * Reference to the page name for linking to posts.
     * @var string
     */
    public $postPage;

    /**
     *
     */
    // public $dateRange;

    public function componentDetails()
    {
        return [
            'name'        => 'Top Visited Component',
            'description' => 'Retrieve the top visited RainLab Blog Posts'
        ];
    }
    /**
     * Definition of propertys
     * @return [array]
     */
    public function defineProperties()
    {
        return [
            'period' =>[
                'title'         => 'Top 10 from:',
                'description'   => '',
                'default'       => 4,
                'type'          => 'dropdown',
                'options' => [
                    '1' => 'Today',
                    '2' => 'Current week',
                    '3' => 'Yesterday',
                    '4' => 'Last week',
                    '5' => 'All time'
                ],
                'showExternalParam' => false
            ],
            'category' =>[
                'title'         => 'Category',
                'description'   => 'Filter result by category. All categories by default',
                'type'          => 'dropdown',
                'placeholder'   => 'Select a category',
                'showExternalParam' => false
            ],
            'postPerPage' => [
                'title'         => 'Post per page',
                'description'   => '',
            ],
            'postPage' => [
                'title'         => 'Post page',
                'description'   => 'Page to show linked posts',
                'type'          => 'dropdown',
                'default'       => 'blog/post',
                'group'         => 'Links',
            ],
            'slug' => [
                'title'         => 'rainlab.blog::lang.settings.post_slug',
                'description'   => 'rainlab.blog::lang.settings.post_slug_description',
                'default'       => '{{ :slug }}',
                'type'          => 'string',
                'group'         => 'Links'
            ]
        ];
    }
    /**
     * prepare Vars function
     * @return [object]
     */
    protected function prepareVars()
    {
        $this->postParam = $this->page['postParam'] = $this->property('postParam');
    }
    /**
     * [getPostPageOptions]
     * @return [array][Blog]
     */
    public function getPostPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }
    /**
     * [getCategoryOptions]
     * @return [array list] [Blog Categories]
     */
    public function getCategoryOptions(){
        $categories =  Category::orderBy('name')->lists('name','id');
        return $categories;
    }
    public function onRun(){
        $this->prepareVars();

        /*Get the category filter*/
        $category = $this->property('category') ? $this->property('category') : null;

        /* Get post page */
        $this->postPage = $this->property('postPage') ? $this->property('postPage') : '404';

        /* Obtenemos los ID de los más visitados en el rango solicitado */
        $topIds = $this->getTop();

        /*
         * Empezamos con el objeto de los posts en general que estén publicados
         */
        $p = Post::isPublished();

        /*
         * De los obtenidos, filtramos por el ID
         */
        $p->whereHas('visits', function($q) use ($topIds) {
                $q->whereIn('post_id', $topIds);
            })
            ->with('visits');

        /*
         * Hacemos el get()
         */

        $topPosts = $p->get();

        /*
         * Organizamos los resultados... no me preguntem solo sé que así salió
         */
        $topPosts = $topPosts->sortByDesc(function ($post, $key){
            return count($post->visits);
        });

        /*
         * Agregamos el helper de la URL
         */
        $topPosts->each(function($post) {
           $post->setUrl($this->postPage,$this->controller);
        });

        /*
         * Mandamos los resultados como variable al componente
         */
        $this->topPosts = $topPosts;

    }
    public function getTop(){
        /*
         * Get date range
         */
        switch($this->property('period')){
            case '1':
                $dateRange = Carbon::today();
            break;
            case '2':
                $fromDate = Carbon::now()->startOfWeek()->format('Y-m-d');
                $toDate = Carbon::now()->endOfWeek()->format('Y-m-d');
            break;
            case '3':
                $dateRange = Carbon::yesterday();
            break;
            case '4':
                $fromDate = Carbon::now()->subDays(7)->startOfWeek()->format('Y/m/d');
                $toDate = Carbon::now()->subDays(7)->endOfWeek()->format('Y/m/d');
            break;
            default:
                $dateRange = Carbon::today();
            break;
        }

        /*
         * Hacemos la consulta en el rango (queda pendiente el filtro por categoria)
         */
        $v = new Visits;
        $v = isset($dateRange) ? $v->where('date',$dateRange) : $v->whereBetween('date', array($fromDate, $toDate));
        $v  ->select('post_id')
                ->selectRaw('sum(visits) as visits, count(post_id) as touchs')
                ->groupBy('post_id')
                ->orderBy('visits','desc')
                ->limit($this->property('postPerPage'));
        $topIds = $v -> lists('post_id');
        return $topIds;
    }
}
