Medication Inventory
QUESTION: When should the inventory decrement from the quantity on hand? We can (a) decrement from the quantity on hand as soon as the medication is prescribed or (b) wait for the BD Pyxis device to tell us the medication has been dispensed.
ANSWER: (B) Have inventory decrement once BD Pyxis says the medication has been dispensed. 
QUESTION: Can you please provide a drug manufacturer list? We want to control the list of manufacturer options for data quality.
ANSWER: There is no set manufacturer list. A list of common manufactures can be provided, but having the ability to add to this list overtime would be ideal.
Reporting
QUESTION: What type of reporting do you all need from the medication management system? We currently have the following reports, but please let us know if you need any others.
Medication Inventory List - Shows you a complete list of medications and their associated warehouses, lot #s, expiration dates, and quantities on hand.
Medication Activity - Shows you a complete list of medications and what quantity of each drug has been dispensed, purchased, destroyed, and transferred for a custom period of time.
Medication Transactions - Shows you medication transactions (medication purchase, edits, deletion, and more) in the inventory by date.
ANSWER: These reports should cover everything we need. No need for other reports.
QUESTION: What happens when an hl7 message errors between PHIX and BD Pyxis? Are y'all planning to send hl7 received confirmation messages and/or error messages? If so, can you please provide examples.
ANSWER: Message acknowledgements will be sent for each message, but it will not include details about whether or not the message failed. We do not send messaging back whether the Hl7 message failed or did not fail, we will receive the message and if it is missing critical information in the message it will fail to process to the PD Pyxis (ES Server) which we will see internally in Pyxis after doing some digging, ultimately the message will fail to post to the ES Server. I hope this question answers your intention of the messaging.

QUESTION: I've attached an example HL7 message for a medication order. Can you please confirm that this is the expected format and required fields (we deidfentified the message and left out PID details)? If you have a validation tool that can provide feedback that would be wonderful as well.
MSH|^~\&|OPENEMR|CLINIC|PYXIS|FACILITY|20251121080710||RDE^O11|D20251121080710|P|2.3
PID|||INV1763737630^^^INV^CLINICID|||^^^^|||||
ORC|NW|ORD1763737630|||||||||20251121080710|||CLINICID
RXE|^^^TB000003758^TEST DRUG 765|5|ML|CAP|10|EA|||WHSE01|MANU 3|
NTE|1|L|New drug added to inventory ‑ cap bottle|
ANSWER: See below
-Missing PV1 segment
-MSH looks good
-PID
--I see missing patient name
--Please confirm CLINICID will be sent instead of MRN for patient type previously discussed for PID.3.5
--PID.5 not valued with patient name
PID.7 (DOB) and PID.8 (SEX) missing
I do not believe we need this to post, but they do have PID.9 (Patient Alias), PID.10 (Race valued
--PID segment seems short ending at PID.10 in your example, the EPIC example I am looking at goes to PID.22, although I do not see these being needed for the message to post with exception to PID.18
--Missing Patient Visit Number (PID.18)
-ORC
--We expect order number in ORC.3, I think you are sending it in ORC.2
--Missing ORC.7 segments we would expect usually matching RXE.1 segments which relate to order expectations such as start/end time, priority, condition, frequency etc
--ORC.7.2 (Timing), ORC.7.4 (Start Time), ORC.7.5 (Stop Time), ORC.7.6 (Priority Code), ORC.7.7 (Condition) Missing, ORC.12 (Ordering Provider)
--Not critical to posting, but based on the EPIC example ORC.9 (Date/Time of Transaction), ORC.10 (Entered By)
--ORC.11 in your example appears to be a date (potentially transaction time?) which doesn’t appear to be appropriate for the expected field of Verified by (ORC.11)
-RXE
--Missing RXE.1.2.1 (Repeat Pattern), RXE.1.6 (Give Dosage Form), RXE.1.7 (Condition), RXE.2.2 (Item Description)
--Inaccurate RXE.1.4 and 1.5 we expect RXE.1.4 (Start Time), RXE.1.5 (Stop Time)
--Inaccurate RXE.2.1 we expect RXE.2.1 (Item Med Code or ID)
--Inaccurate RXE.3 we expect RXE.3 (Give amount minimum)
--Inaccurate RXE.4 we expect what I believe to be the strength unit in RXE.5.1 (Give Units
--Inaccurate RXE.10 we expect RXE.10 (Dispense Amount\
-Note Administration Instructions, if exist, will be sent to RXE.7.2.  I see you are using NTE I usually see this if populated potential alternative location for additional information administration instructions or potentially special instructions (though we have a field for that in RXE21.2 [Supplier instructions text]), not a required field, but one we can map to show to nursing when they remove meds.  We traditionally expect to see admin instructions only sent to RXE.7.2.  The values in NTE from your example I am not sure what details we are attempting to send to Pyxis
--Will not prevent from posting and most of these don’t have a place in Pyxis, but I figured I would call out I see in this EPIC example they are sending RXE.11 (Dispense units), RXE.13 (MD DEA Number), RXE.25 (Give Strength), RXE.26 (Give Strength Units), RXE.27 (Give indication)
 -TQ1
--During the IWBR you referenced you should be able to send TQ1 messages, is this something you intend to send to Pyxis?  The advantage of this is if possible to send they do process things like Now and Routine orders better than base RXE/ORC messaging
RXR
-Missing all segments, we require RXR.1.1 to be valued to post messaging, optional to include RXR.1.2, but will post to server if value
QUESTION: Can you confirm that we should be validating our outgoing HL7 with the "Pharmacy Orders" specifications on chapter 5 of the BD Pyxis Guide? 
ANSWER: Yes, follow "pharmacy Orders" guide.
QUESTION: Can you please provide examples of how you will provide dispensed HL7 messages
QUESTION: When medications are removed form Pyxis (expired or otherwise) will you send an hl7 message to update the inventory within OpenEMR? Or will the staff need to remove the drug from OpenEMR and then remove the drug from Pyxis?
We just want to confirm which actions will occur within OpenEMR and require an hl7 transaction outbound from OpenEMR.
ANSWER: (6,7) Medstation activity is communicated to the Med ES Server which is communicated to the host system automatically with a working interface connection.  Pyxis will send a ZPM message (reference Page 24 to 30) when inventory transactions are created (Load/Refill,Unload etc) to PHIX.  We also will send dispensing activity to PHIX in the form of a DFT message (reference Page 30 to 33) which most vendors like us to send a ZPM segment along with for inventory tracking purposes.  Many host systems only are concerned with Load and Unload with considerations for hierarchy considerations, but some systems can manage perpetual inventory where they know the exact values the stations have which those ZPM message attached to the DFT messages help to maintain.  I understand the customer intends to use charge on admin, so if that is the case we will still send DFT messages which you can use for references and inventory management if desired.