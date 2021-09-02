<?php

namespace App\Controller;

use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class HolidayController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function index(): Response
    {
        $countries = $this->getSupportedCountries();

        return $this->render('holidays.html.twig', [
            'countries' => $countries,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function search(Request $request, ValidatorInterface $validator): Response
    {
        $country = $request->query->get('country');
        $year = $request->query->get('year');

        $input = ['country' => $country, 'year' => $year];

        $constraints = new Collection([
            'country' => [new Length(['min' => 3, 'max' => 3]), new NotBlank],
            'year' => [new Length(['min' => 4, 'max' => 4]), new notBlank]
        ]);

        $errors = $validator->validate($input, $constraints);

        $countries = $this->getSupportedCountries();

        if ($errors->count() <= 0) {
            $response = $this->client->request(
                'GET',
                "https://kayaposoft.com/enrico/json/v2.0?action=getHolidaysForYear&year=$year&country=$country&holidayType=public_holiday"
            );

            $responseData = $response->toArray();

            //check if we get an error from API
            if (isset($responseData['error'])) {
                return $this->render('holidays.html.twig', [
                    'countries' => $countries,
                    'apiError' => $responseData['error']
                ]);
            }

            $data = $this->formatHolidaysForYearByMonth($responseData);
            $totalHolidays = count($responseData);
        } else {
            return $this->render('holidays.html.twig', [
                'countries' => $countries,
                'errors' => $errors,
            ]);
        }

        return $this->render('holidays.html.twig', [
            'countries' => $countries,
            'data' => $data,
            'country' => strtolower($country),
            'totalHolidays' => $totalHolidays,
            'year' => $year
        ]);
    }

    /**
     * @return array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function getSupportedCountries(): array
    {
        $response = $this->client->request(
            'GET',
            "https://kayaposoft.com/enrico/json/v2.0/?action=getSupportedCountries"
        );

        $responseData = $response->toArray();

        $countries = [];
        foreach ($responseData as $item) {
            $countries[$item['countryCode']] = $item['fullName'];
        }

        return $countries;
    }

    private function formatHolidaysForYearByMonth(array $holidays): array
    {
        $holidaysGroupedByMonth = [];

        foreach ($holidays as $holiday) {
            $month = DateTime::createFromFormat('!m', $holiday['date']['month'])->format('F');
            $holidaysGroupedByMonth[$month][] = [
                'day' => $holiday['date']['day'],
                'dayOfWeek' => $holiday['date']['dayOfWeek'],
                'text' => $holiday['name'][array_search('en', array_column($holiday['name'], 'lang'), true)]['text']
            ];
        }

        return $holidaysGroupedByMonth;
    }
}