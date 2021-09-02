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
            $longestFreeDaySequence = $this->getLongestFreeDayPeriodInRow($responseData);
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
            'year' => $year,
            'totalHolidays' => $totalHolidays,
            'statusToday' => $this->getStatusToday($country),
            'longestFreeDaySequence' => $longestFreeDaySequence
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

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function getStatusToday($country): string
    {
        if ($this->isWorkDay($country)) {
            return 'Work day';
        }

        if ($this->isPublicHoliday($country)) {
            return 'Public Holiday';
        }

        return 'Free day';
    }

    /**
     * @param string $country
     * @return bool
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function isPublicHoliday(string $country): bool
    {
        $date = date('d-m-Y');

        $response = $this->client->request(
            'GET',
            "https://kayaposoft.com/enrico/json/v2.0?action=isPublicHoliday&date=$date&country=$country"
        );

        $responseData = $response->toArray();

        return $responseData['isPublicHoliday'];
    }

    /**
     * @param string $country
     * @return bool
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function isWorkDay(string $country): bool
    {
        $date = date('d-m-Y');

        $response = $this->client->request(
            'GET',
            "https://kayaposoft.com/enrico/json/v2.0/?action=isWorkDay&date=$date&country=$country"
        );

        $responseData = $response->toArray();

        return $responseData['isWorkDay'];
    }

    private function getLongestFreeDayPeriodInRow($data): array
    {
        //format API response to valid dates
        $dates = [];
        foreach ($data as $item) {
            $dateArray = $item['date'];
            $dateString = "{$dateArray['day']}-{$dateArray['month']}-{$dateArray['year']}";
            $dates[] = DateTime::createFromFormat('j-n-Y', $dateString)->format('d-m-Y');
        }

        //add weekends to dates
        $datesWithWeekends = [];
        foreach($dates as $item){
            $datesWithWeekends[] = $item;

            //if this date is friday add 2 days ir saturday add one day
            switch(date("w", strtotime($item))){
                case 5:
                    $saturday = date("d-m-Y", strtotime("$item +1 day"));
                    $sunday = date("d-m-Y", strtotime("$item +2 day"));
                    if(!in_array($saturday, $dates, true)){
                        $datesWithWeekends[] = $saturday;
                    }

                    if(!in_array($sunday, $dates, true)){
                        $datesWithWeekends[] = $sunday;
                    }
                    break;
                case 6:
                    $sunday = date("d-m-Y", strtotime("$item +1 day"));

                    if(!in_array($sunday, $dates, true)){
                        $datesWithWeekends[] = $sunday;
                    }
                    break;
            }
        }


        //list sequential dates in arrays
        $grouped = [];
        $i = 0;
        $lastDate = null;
        $result = [];
        foreach ($datesWithWeekends as $date) {
            if ($date !== date("d-m-Y", strtotime("$lastDate +1 day"))) {
                ++$i;
            }
            $grouped[$i][] = $lastDate = $date;
        }

        foreach ($grouped as $group) {
            $result[] = $group;
        }

        //find longest free day sequences
        $lastLongest = 0;
        $longestPeriodKeys = [];
        foreach ($result as $key => $item){
            $periodLength = count($item);
            if($periodLength > $lastLongest){
                $lastLongest = $periodLength;
                $longestPeriodKeys = [$key];
            }

            if(($periodLength === $lastLongest) && !in_array($key, $longestPeriodKeys, true)) {
                $longestPeriodKeys[] = $key;
            }
        }

        //return longest free day periods
        $returnData = [];
        foreach ($longestPeriodKeys as $key){
            $returnData[] = $result[$key];
        }

        return $returnData;
    }
}