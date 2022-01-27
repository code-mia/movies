<?php
namespace App\Service;
use Symfony\Contracts\HttpClient\HttpClientInterface;
class callApi {
    private $client;
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }
    public function getApi(string $var): array {

        $response = $this->client->request(
            'GET',
            'http://www.omdbapi.com/?apikey='.$_ENV['KEY'].$var
        );
        return $response->toArray();
    }

    public function getMovieByTitle($title): array {
        return $this->getApi('&t='.$title);
    }


}
