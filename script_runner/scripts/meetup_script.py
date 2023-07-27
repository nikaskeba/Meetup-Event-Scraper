import requests
from bs4 import BeautifulSoup
from datetime import timedelta
from dateutil.parser import parse
from dateutil.tz import gettz
from ics import Calendar, Event
def scrape_website(url):
    response = requests.get(url)
    soup = BeautifulSoup(response.text, 'html.parser')

    # Lists to hold the text of h2, span, p tags, href of a tags and address
    h2_text = [tag.get_text() for tag in soup.find_all('h2') if "Upcoming" not in tag.get_text()]
    div_text = [tag.get_text().replace('UTC', 'PDT') for tag in soup.find_all('div', class_='eventTimeDisplay') if "Coordinated" not in tag.get_text()]
    p_text = [tag.get_text() for tag in soup.find_all('p', class_='description-markdown--p')]
    a_href = ['https://meetup.com' + tag['href'] for tag in soup.find_all('a', class_='eventCard--link')]
    address_text = [tag.get_text() for tag in soup.find_all('address')]

    # Zip the lists together, but only include entries where the corresponding div_text entry does not contain "Coordinated"
    combined_text = [(h2, div, p, a, address) for h2, div, p, a, address in zip(h2_text, div_text, p_text, a_href, address_text) if "Coordinated" not in div]

    return soup.prettify(), combined_text

def write_to_file(filename, content):
    with open(filename, 'w') as file:
        file.write(content)

def write_list_to_file(filename, list_content):
    with open(filename, 'w') as file:
        for h2, div, p, a, address in list_content:
            file.write(h2 + '\n' + div + '\n' + p + '\n' + a + '\n' + address + '\n')

def create_ics_file(filename, list_content):
    cal = Calendar()

    for h2, div, p, a, address in list_content:
        event = Event()
        event.name = h2
        event.description = p
        event.url = a
        event.location = address

        # parse the datetime string, assuming it is in the format like "Mon, Jul 24, 2023, 3:00 PM PDT"
        start_time = parse(div, tzinfos={"PDT": gettz("America/Los_Angeles")})
        event.begin = start_time

        # end time is one hour after start time
        event.end = start_time + timedelta(hours=1)

        cal.events.add(event)

    with open(filename, 'w') as file:
        file.writelines(cal)
def append_to_file(filename, content):
    with open(filename, 'a') as file:
        file.write(content + '\n')

def append_list_to_file(filename, list_content):
    with open(filename, 'a') as file:
        for h2, div, p, a, address in list_content:
            file.write(h2 + '\n' + div + '\n' + p + '\n' + a + '\n' + address + '\n')

def append_to_ics_file(filename, list_content):
    try:
        with open(filename, 'r') as file:
            cal = Calendar(file.read())
    except FileNotFoundError:
        cal = Calendar()

    for h2, div, p, a, address in list_content:
        event = Event()
        event.name = h2
        event.description = p
        event.url = a
        event.location = address

        # parse the datetime string, assuming it is in the format like "Mon, Jul 24, 2023, 3:00 PM PDT"
        start_time = parse(div, tzinfos={"PDT": gettz("America/Los_Angeles")})
        event.begin = start_time

        # end time is one hour after start time
        event.end = start_time + timedelta(hours=1)

        cal.events.add(event)

    with open(filename, 'w') as file:
        file.writelines(cal)
import os

def delete_file(filename):
    if os.path.exists(filename):
        os.remove(filename)

if __name__ == '__main__':
    # delete old files
 
    delete_file('all_combined.txt')
    delete_file('all_events.ics')


    with open('urls.txt', 'r') as file:
        urls = file.read().splitlines()
        
    for url in urls:
        content, combined_text = scrape_website(url)
     
        append_list_to_file('all_combined.txt', combined_text)
        append_to_ics_file('all_events.ics', combined_text)