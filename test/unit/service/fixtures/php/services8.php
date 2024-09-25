class ProjectServiceContainer extends sfServiceContainer
{
    protected $shared = [];

    public function __construct()
    {
        parent::__construct($this->getDefaultParameters());
    }

    protected function getDefaultParameters()
    {
        return [
            'foo' => 'bar',
            'bar' => 'foo is %foo bar',
            'values' => [true, false, null, 0, 1000.3, 'true', 'false', 'null', ],
        ];
    }
}
