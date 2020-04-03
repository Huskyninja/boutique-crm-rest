# Boutique CRM Web Service Library
A collection of REST endpoints for a handful of boutique CRMs.

## About
This is a collection of RESTful endpoints (and one cron job) that was used to stitch a number of niche CRM's client data togeather.
* em2en - Sends emfluence data to enquire. Triggered by an emfluence form's submission and requires mapping the target's information in a MySQL database.
* em2sh - Sends emfluence data to Sherpa's v1 endpoint. Triggered by an emfluence form's submission and requires mapping the target's information in a MySQL database. Community ID may be placed in the emfluence 'notes' field if the database must be bypassed.
* em2sh2 - Sends emfluence data to Sherpa's v2 endpoint. Triggered by an emfluence form's submission and requires mapping the target's information in a MySQL database.
* queue-enflusher - Scrapes recent additions to the enquire individual-new endpoint to emfluence. Can identify target group ids based on enquire community ids. Must be triggered to execute (like with cron).
* un2em - Sends unbounce data to emfluence. Triggered by an unbounce form submission, and requires mapping the target's information in a MySQL database.
* wp2y - Here's an esoteric one... Sends form data to Yardi based on field names (visible, hidden, or otherwise). Designed originally for use on one form with WordPress but can be used to wrap any form into the necessary SOAP bubble, as long as the form has all the required info.

Although several of these connectors are past their sell-by-date, they are shared here with the hope that thier information can help you with your own projects.
