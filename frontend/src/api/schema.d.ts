export interface paths {
    "/api/admin/org/{orgSlug}/attention/summary": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Counts of unresolved (open + acknowledged) items per type, for the dashboard badge */
        get: operations["get_api_admin_attention_summary"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/attention/{itemId}/actions/{action}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Executes a command action of the item (acknowledge, resolve, dismiss) through the command bus
         * @description Resolving and dismissing additionally need payment.exception.resolve. Link actions are performed in the owning module.
         */
        post: operations["post_api_admin_attention_action"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/attention/{itemId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** One queue item with the actions it offers */
        get: operations["get_api_admin_attention_detail"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/attention": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The "needs attention" queue (ADM-03), newest first, filterable by type, state and event */
        get: operations["get_api_admin_attention_list"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/audit": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Audit history of one entity, newest first, optionally only one kind of actor (AUD-03, AUD-04) */
        get: operations["get_api_admin_audit_entity"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/audit-log": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The organizer-wide audit log, newest first (owners only) */
        get: operations["get_api_admin_audit_log"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Events of the organizer, newest first */
        get: operations["get_api_admin_event_list"];
        put?: never;
        /** Create a draft event (the "simple" mode adds the implicit activity and variant) */
        post: operations["post_api_admin_event_create"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** One event with its activities and variants (draft included) */
        get: operations["get_api_admin_event_detail"];
        /** Replace the descriptive data of an event (optimistic lock with If-Match: <version>) */
        put: operations["put_api_admin_event_update"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/payment-rules": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        /** Payment and cancellation rules of the event (EVT-10) */
        put: operations["put_api_admin_event_payment_rules"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/publish": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Publish a draft event: rules check, immutable form version 1, capacity buckets */
        post: operations["post_api_admin_event_publish"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/cancel": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Cancel a published event (owner): closes all registrations, starts the cancellation process */
        post: operations["post_api_admin_event_cancel"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/complete": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Complete a published event after it has ended */
        post: operations["post_api_admin_event_complete"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/archive": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Archive a completed or cancelled event */
        post: operations["post_api_admin_event_archive"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/registration/{transition}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Open, pause, resume or close the registration of the event (`cascade` also moves the activities) */
        post: operations["post_api_admin_event_registration"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/activities/{activityId}/registration/{transition}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Open, pause, resume or close the registration of one activity (opens only while the event registration is open) */
        post: operations["post_api_admin_activity_registration"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/form": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The draft and the latest published version of the registration form, and the list of versions */
        get: operations["get_api_admin_form_overview"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/form/versions/{number}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** One version of the form (draft or published) */
        get: operations["get_api_admin_form_version"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/form/draft": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        /** Replace the draft of the form; after the publication this starts the draft of the next version */
        put: operations["put_api_admin_form_edit_draft"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/form/publish": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Freeze the draft as the next immutable form version of a published event (FRM-08, FRM-09) */
        post: operations["post_api_admin_form_publish"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/form-field-types": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Field types the editor offers, with their constraints and semantic fields */
        get: operations["get_api_admin_form_field_types"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/program": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The program of the event, by start time (all visibilities) */
        get: operations["get_api_admin_program_list"];
        put?: never;
        /** Add a program item, optionally linked to an activity */
        post: operations["post_api_admin_program_add"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/program/{itemId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        /** Replace a program item; `important` offers the notification of the affected participants */
        put: operations["put_api_admin_program_update"];
        post?: never;
        /** Remove a program item */
        delete: operations["delete_api_admin_program_remove"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/news": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** News posts of the event, drafts included */
        get: operations["get_api_admin_news_list"];
        put?: never;
        /** Create a news post (`publish` publishes at once, `broadcast` also sends it by e-mail) */
        post: operations["post_api_admin_news_create"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/news/{postId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        /** Edit a draft news post */
        put: operations["put_api_admin_news_update"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/news/{postId}/publish": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Publish a news post */
        post: operations["post_api_admin_news_publish"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/events": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Public events of the organizer (published and completed, visibility public) */
        get: operations["get_api_public_event_list"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/events/{eventSlug}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** A published event: description, activities, variants with prices and availability, program, news and the form schema */
        get: operations["get_api_public_event_detail"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/events/{eventSlug}/form": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The current registration form of a published event (fields, JSON Schema fragments, conditions) */
        get: operations["get_api_public_event_form"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/events/{eventSlug}/availability": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Free places of a published event by event, activity and variant (available / low / sold_out / unlimited) */
        get: operations["get_api_public_event_availability"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/activities": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Add an activity (a published event gets its capacity bucket at once) */
        post: operations["post_api_admin_activity_add"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/activities/{activityId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        /** Replace the data of an activity; moving the start of a published activity emits activity_rescheduled */
        put: operations["put_api_admin_activity_update"];
        post?: never;
        /** Remove an activity with its variants (draft events only) */
        delete: operations["delete_api_admin_activity_remove"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/activities/{activityId}/variants": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Add a variant (price level) to an activity */
        post: operations["post_api_admin_variant_add"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/variants/{variantId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        /** Replace the data of a variant; a price change of a published event gives the price list a new version */
        put: operations["put_api_admin_variant_update"];
        post?: never;
        /** Remove a variant (draft events only) */
        delete: operations["delete_api_admin_variant_remove"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/capacity": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Capacity buckets of an event: limit, held, confirmed, available per level */
        get: operations["get_api_admin_event_capacity"];
        put?: never;
        /** Set the capacity limit of the event, an activity or a variant (CAP-06; a published event needs a reason) */
        post: operations["post_api_admin_capacity_change"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/org/{orgSlug}/events/{eventId}/lists/{listId}/check-ins": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Check a participant in. The exception matrix decides: allow, warn (acknowledge it), escalate (202, the escalation is raised) or block (409) */
        post: operations["post_api_check_in_record"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/events/{eventId}/lists/{listId}/check-ins": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Check a participant in. The exception matrix decides: allow, warn (acknowledge it), escalate (202, the escalation is raised) or block (409) */
        post: operations["post_api_check_in_record_device"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/org/{orgSlug}/events/{eventId}/lists/{listId}/cash-check-ins": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Take cash from an unpaid participant and check them in, in one transaction. A device needs the organizer policy `check_in_may_record_cash` */
        post: operations["post_api_check_in_cash"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/events/{eventId}/lists/{listId}/cash-check-ins": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Take cash from an unpaid participant and check them in, in one transaction. A device needs the organizer policy `check_in_may_record_cash` */
        post: operations["post_api_check_in_cash_device"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/org/{orgSlug}/events/{eventId}/lists/{listId}/escalations": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Pass the situation of a participant on to a manager (raises checkin.escalation_raised; Attention opens an item) */
        post: operations["post_api_check_in_escalate"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/events/{eventId}/lists/{listId}/escalations": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Pass the situation of a participant on to a manager (raises checkin.escalation_raised; Attention opens an item) */
        post: operations["post_api_check_in_escalate_device"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/org/{orgSlug}/events/{eventId}/check-ins/{checkInId}/void": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Void a check-in (the participant can be checked in again) */
        post: operations["post_api_check_in_void"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/org/{orgSlug}/events/{eventId}/check-ins/{checkInId}/correct": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Correct a check-in: the row is voided and a new valid one with the corrected station / time takes over */
        post: operations["post_api_check_in_correct"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/org/{orgSlug}/events/{eventId}/lists": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Check-in lists of an event (one per activity) with expected and arrived counters */
        get: operations["get_api_check_in_lists"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/events/{eventId}/lists": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Check-in lists of an event (one per activity) with expected and arrived counters */
        get: operations["get_api_check_in_lists_device"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/org/{orgSlug}/events/{eventId}/overview": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Operational overview of the event: expected / arrived / missing / unpaid / cash received, per activity */
        get: operations["get_api_check_in_overview"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/events/{eventId}/overview": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Operational overview of the event: expected / arrived / missing / unpaid / cash received, per activity */
        get: operations["get_api_check_in_overview_device"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/org/{orgSlug}/events/{eventId}/lists/{listId}/participants": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Search the participants of a check-in list by name or registration code */
        get: operations["get_api_check_in_search"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/events/{eventId}/lists/{listId}/participants": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Search the participants of a check-in list by name or registration code */
        get: operations["get_api_check_in_search_device"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/org/{orgSlug}/events/{eventId}/lists/{listId}/lookup": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Look a participant up by QR token, registration code or id: the check-in detail and the verdict of the exception matrix (writes nothing) */
        post: operations["post_api_check_in_lookup"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/events/{eventId}/lists/{listId}/lookup": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Look a participant up by QR token, registration code or id: the check-in detail and the verdict of the exception matrix (writes nothing) */
        post: operations["post_api_check_in_lookup_device"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/campaigns": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Campaigns, newest first, with the number of deliveries per state */
        get: operations["get_api_admin_campaign_list"];
        put?: never;
        /** Creates a campaign as a draft; without subject and body the wording of templateKey is used */
        post: operations["post_api_admin_campaign_create"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/campaigns/preview": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Without saving: the rendered message and how many deliveries the audience gives right now */
        post: operations["post_api_admin_campaign_preview"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/campaigns/{campaignId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** A campaign with its wording, targeting and delivery counts */
        get: operations["get_api_admin_campaign_get"];
        /** Edits a draft campaign */
        put: operations["put_api_admin_campaign_update"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/campaigns/{campaignId}/send": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Sends the campaign now: one delivery per orderer and activity, each re-checked just before sending */
        post: operations["post_api_admin_campaign_send"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/campaigns/{campaignId}/schedule": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Schedules the campaign for a future moment */
        post: operations["post_api_admin_campaign_schedule"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/campaigns/{campaignId}/cancel": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Cancels a draft or scheduled campaign */
        post: operations["post_api_admin_campaign_cancel"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/deliveries": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Delivery history (state, time, error, masked recipient), filterable by campaign, state and order */
        get: operations["get_api_admin_delivery_list"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/campaigns/{campaignId}/deliveries": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Delivery history of one campaign */
        get: operations["get_api_admin_campaign_deliveries"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/campaigns/{campaignId}/deliveries/retry": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Retries only the failed deliveries of a campaign (no duplicates: same idempotency keys) */
        post: operations["post_api_admin_campaign_retry"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/deliveries/{deliveryId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** One delivery */
        get: operations["get_api_admin_delivery_get"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/deliveries/{deliveryId}/retry": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Retries one failed delivery */
        post: operations["post_api_admin_delivery_retry"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/message-templates": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Every message template with the text that applies (own or built-in), optionally of one key and language */
        get: operations["get_api_admin_template_list"];
        put?: never;
        /** Creates the organizer's own wording of a message in one language */
        post: operations["post_api_admin_template_create"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/message-templates/variables": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The variables a template may use */
        get: operations["get_api_admin_template_variables"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/message-templates/preview": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Renders a text (or the template of a key) with sample data in the sandbox */
        post: operations["post_api_admin_template_preview"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/message-templates/{templateId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** One own template */
        get: operations["get_api_admin_template_get"];
        /** Changes the subject and body of an own template */
        put: operations["put_api_admin_template_update"];
        post?: never;
        /** Removes the own wording; the built-in text applies again */
        delete: operations["delete_api_admin_template_delete"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/communication/reminder-settings": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The reminder schedule (the defaults until it is changed) */
        get: operations["get_api_admin_reminder_settings_get"];
        /** Changes the reminder schedule; applies to reminders planned from now on */
        put: operations["put_api_admin_reminder_settings_put"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/suppressions": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The suppression list (ids and scopes, never addresses) */
        get: operations["get_api_admin_suppression_list"];
        put?: never;
        /** Puts an address on the suppression list (idempotent) */
        post: operations["post_api_admin_suppression_add"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/suppressions/{suppressionId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post?: never;
        /** Takes an address off the suppression list */
        delete: operations["delete_api_admin_suppression_lift"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/collections": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Collections of the event, drafts included */
        get: operations["get_api_admin_content_collections"];
        put?: never;
        /** Create a draft gallery (POST-01) or external collection (POST-02), public or link-only (POST-03) */
        post: operations["post_api_admin_content_collection_create"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/collections/{collectionId}/photos": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Photos of a collection */
        get: operations["get_api_admin_content_photos"];
        put?: never;
        /** Upload a photo into a gallery (multipart: photo + description, author, eventPart, position); the thumbnail follows asynchronously */
        post: operations["post_api_admin_content_photo_upload"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/collections/{collectionId}/publish": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Publish a collection (emits content.collection_published) */
        post: operations["post_api_admin_content_collection_publish"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/collections/{collectionId}/unpublish": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Take a collection back to draft (e.g. a photo has to go) */
        post: operations["post_api_admin_content_collection_unpublish"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/collections/{collectionId}/archive": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        /** Put a collection into (or out of) the public archive (POST-07) */
        put: operations["put_api_admin_content_collection_archive"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/materials": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Materials of the event, drafts included */
        get: operations["get_api_admin_content_materials"];
        put?: never;
        /** Add a draft material link: results, video, document, partners (POST-04) */
        post: operations["post_api_admin_content_material_add"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/materials/{materialId}/publish": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Publish a material */
        post: operations["post_api_admin_content_material_publish"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/materials/{materialId}/unpublish": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Take a material back to draft */
        post: operations["post_api_admin_content_material_unpublish"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/materials/{materialId}/archive": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        /** Put a material into (or out of) the public archive (POST-07) */
        put: operations["put_api_admin_content_material_archive"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/closing-message": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Propose a draft "thank you" campaign to the checked-in participants (POST-05); send it in Communication */
        post: operations["post_api_admin_content_closing_message"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/events/{eventSlug}/content": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Published public collections and materials of a public event page (PUB-06) */
        get: operations["get_api_public_content"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/events/{eventSlug}/archive": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The public archive of a completed or archived event: only designated content (POST-07) */
        get: operations["get_api_public_content_archive"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/events/{eventSlug}/collections/{collectionId}/photos": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Photos of a published public collection */
        get: operations["get_api_public_content_photos"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/events/{eventSlug}/collection-links/{token}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** A link-only collection opened through its unguessable link (POST-03) */
        get: operations["get_api_public_content_link"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/content/photos/{photoId}/{variant}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The image of a published photo (a link-only one needs ?token=) */
        get: operations["get_api_public_content_photo_file"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/device-tokens": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Device tokens of the organizer (never including the secret) */
        get: operations["get_api_admin_device_tokens"];
        put?: never;
        /** Issue a device token, optionally bound to one check-in list. The secret is returned once. */
        post: operations["post_api_admin_device_token_issue"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/device-tokens/{deviceTokenId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post?: never;
        /** Revoke a device token (the device is locked out at once) */
        delete: operations["delete_api_admin_device_token_revoke"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/auth/invitations/preview": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** What an invitation offers (organizer, role, whether the e-mail already has an account) */
        post: operations["post_api_auth_invitation_preview"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/auth/invitations/accept": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Accept a team invitation
         * @description Signed in as the invited account: only `token`. Not signed in and no account for the invited e-mail: `token`, `password` (10+ characters) and `displayName` create the account and sign in. An existing account must sign in first (409 sign_in_required).
         */
        post: operations["post_api_auth_invitation_accept"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/auth/login": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Sign in a team member (session cookie)
         * @description Send the `csrf-token` header (any random token of 24+ characters) like on every unsafe request.
         */
        post: operations["post_api_auth_login"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/auth/me": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Who am I: the signed-in member and the organizers they belong to */
        get: operations["get_api_auth_me"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Detail of the organizer the signed-in member belongs to */
        get: operations["get_api_admin_organizer_detail"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/profile": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Profile of the organizer (contact, billing data, branding) */
        get: operations["get_api_admin_organizer_profile"];
        /** Replace the profile of the organizer (owner only). Omitted optional fields are cleared. */
        put: operations["put_api_admin_organizer_profile_update"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/payment-settings": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Payment settings: defaults, policy exceptions and the Pairrot connection state */
        get: operations["get_api_admin_payment_settings"];
        /** Replace the payment defaults and policy exceptions */
        put: operations["put_api_admin_payment_settings_update"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/payment-settings/pairrot": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Connect the bank account through Pairrot (activates the subtenant, connects the account). Repeatable. */
        post: operations["post_api_admin_payment_settings_pairrot"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/auth/sign-up": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Register a new organizer with its owner account and sign in
         * @description Send the `csrf-token` header like on every unsafe request. The organizer slug becomes part of every URL.
         */
        post: operations["post_api_auth_sign_up"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/members": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Members of the team with role and event scope */
        get: operations["get_api_admin_team_members"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/members/{membershipId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        /** Change the role and the event scope of a member */
        put: operations["put_api_admin_team_member_change"];
        post?: never;
        /** Remove a member from the team (their access ends at once) */
        delete: operations["delete_api_admin_team_member_remove"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/invitations": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Open invitations */
        get: operations["get_api_admin_team_invitations"];
        put?: never;
        /** Invite a person by e-mail (the message is sent by Communication; a pending invitation for the same e-mail is replaced) */
        post: operations["post_api_admin_team_invite"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/invitations/{invitationId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post?: never;
        /** Revoke an open invitation */
        delete: operations["delete_api_admin_team_invitation_revoke"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/check-in/whoami": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Who is calling the check-in API: a device (organizer, check-in list) or a team member */
        get: operations["get_api_check_in_whoami"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/legal/confirmations": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Confirmation records, newest first; filter by requirement, participation, order or decision */
        get: operations["get_api_admin_legal_confirmation_list"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/legal/documents": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** List legal documents, optionally of one level and scope */
        get: operations["get_api_admin_legal_document_list"];
        put?: never;
        /** Create an empty legal document (organizer, event or activity level) */
        post: operations["post_api_admin_legal_document_create"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/legal/documents/{documentId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** A legal document with all its versions */
        get: operations["get_api_admin_legal_document_get"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/legal/documents/{documentId}/versions": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Upload the next version as a draft: Markdown text, a base64 PDF or an external link (snapshot taken now) */
        post: operations["post_api_admin_legal_version_draft"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/legal/documents/{documentId}/versions/{versionId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** One version incl. its text (draft or published) */
        get: operations["get_api_admin_legal_version_get"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/legal/documents/{documentId}/versions/{versionId}/publish": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Publish a draft: it becomes the current immutable version; optionally everybody must confirm again */
        post: operations["post_api_admin_legal_version_publish"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/legal/versions/{versionId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** A published version of a legal document (metadata and, for text, the Markdown text) */
        get: operations["get_api_public_legal_version"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/legal/versions/{versionId}/content": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The raw bytes of a published version (Markdown, HTML, plain text or PDF) */
        get: operations["get_api_public_legal_version_content"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/legal/content/{sha256}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The raw bytes of a published version by the SHA-256 of its content */
        get: operations["get_api_public_legal_content_by_hash"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/legal/requirements": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** List requirements, optionally of one document */
        get: operations["get_api_admin_legal_requirement_list"];
        put?: never;
        /** Require a document to be acknowledged or confirmed (a voluntary consent can be neither mandatory nor pre-checked) */
        post: operations["post_api_admin_legal_requirement_define"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/legal/requirements/{requirementId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        /** Replace the editable properties of a requirement */
        put: operations["put_api_admin_legal_requirement_change"];
        post?: never;
        /** Stop applying a requirement to new registrations (the confirmations stay) */
        delete: operations["delete_api_admin_legal_requirement_archive"];
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/legal/preview": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** What applies to a registration for an event (and one activity and variant): documents, versions, who confirms */
        get: operations["get_api_admin_legal_preview"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/unmatched-transactions/{transactionId}/allocate": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Allocate an unmatched bank transaction to an order (asks Pairrot; the money arrives via the inbox) */
        post: operations["post_api_admin_payments_allocate_unmatched"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/refund-tasks/{taskId}/cancel": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Cancel an open refund task (no money is returned) */
        post: operations["post_api_admin_payments_cancel_refund_task"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/orders/{orderId}/ledger/{entryId}/correction": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Correct a cash ledger entry (compensating entry with a reason) */
        post: operations["post_api_admin_payments_correct_cash"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/refund-tasks": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** List refund tasks */
        get: operations["get_api_admin_payments_refund_tasks"];
        put?: never;
        /** Open a refund task for an order */
        post: operations["post_api_admin_payments_open_refund_task"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/orders/{orderId}/finance": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Financial state of an order with its payment requests */
        get: operations["get_api_admin_payments_order_finance"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/orders/{orderId}/ledger": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Ledger entries of an order */
        get: operations["get_api_admin_payments_order_ledger"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/payments/connection": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Pairrot connection status of the organizer */
        get: operations["get_api_admin_payments_connection"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/webhooks/pairrot": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Receive a Pairrot webhook (signed with Pairrot-Webhook-Id / -Timestamp / -Signature) */
        post: operations["post_api_webhooks_pairrot"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/orders/{orderId}/cash": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Record cash for an order */
        post: operations["post_api_admin_payments_record_cash"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/orders/{orderId}/manual-payments": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Book a payment outside Pairrot (manual correction with a reason) */
        post: operations["post_api_admin_payments_manual_payment"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/payment-requests/{paymentRequestId}/retry": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Retry creating a failed payment request */
        post: operations["post_api_admin_payments_retry_request"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/refund-tasks/{taskId}/settle": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Settle a refund task */
        post: operations["post_api_admin_payments_settle_refund_task"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/unmatched-transactions": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Unmatched bank transactions (reconciliation queue) */
        get: operations["get_api_admin_payments_unmatched_transactions"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/unmatched-transactions/{transactionId}/candidates": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Orders that could own an unmatched transaction (same variable symbol or amount) */
        get: operations["get_api_admin_payments_unmatched_candidates"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/participations": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Participants of an event with the four dimensions of each participation (state, legal, answers, finance) */
        get: operations["get_api_admin_participation_list"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/participations/{participationId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** One participation */
        get: operations["get_api_admin_participation_detail"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/orders/{orderId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** An order with its participations and the financial state */
        get: operations["get_api_admin_order_detail"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/participations/{participationId}/cancel": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Cancel a participation as the organizer (REG-14); the reason is mandatory. Seats go back, the amount due drops, Payments opens a refund task when money was paid. */
        post: operations["post_api_admin_participation_cancel"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/participations/{participationId}/restore": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Restore a participation flagged after a late payment, if there is room (CAP-05). The reason is mandatory. */
        post: operations["post_api_admin_participation_restore"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/admin/org/{orgSlug}/events/{eventId}/form-preview": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Preview of the registration form for a chosen combination of activities and variants (FRM-11): fields per person and for the order contact, legal documents to confirm and by whom. Works on the published form version. */
        post: operations["post_api_admin_form_preview"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/events/{eventSlug}/registration-form": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** The registration form for a chosen combination of activities and variants: fields per person, fields of the order contact, legal documents to confirm and by whom, plus the signed antispam timestamp */
        post: operations["post_api_public_registration_form"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/orgs/{orgSlug}/events/{eventSlug}/orders": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Place an order (REG-01..03, REG-06): N persons, M participations, one payment. Header Idempotency-Key is mandatory. The response has no payment data: instructions appear on the self-service page when they exist. */
        post: operations["post_api_public_order_create"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/self-service/order": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** The order behind the link: persons and participations with states, QR codes, open confirmations, payment state and instructions (REG-08) */
        get: operations["get_api_self_service_order"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/self-service/participations/{participationId}/cancel": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Cancel a participation of the order, if the rules of the event allow it (REG-09) */
        post: operations["post_api_self_service_cancel"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/self-service/persons/{personId}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        /** Correct the answers of a person of the order (REG-09): the new answers replace the old ones as a whole */
        put: operations["put_api_self_service_correct_person"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/self-service/contact": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        /** Correct the contact of the order, the order-scoped answers of the form (REG-09), e.g. a typo in the e-mail */
        put: operations["put_api_self_service_correct_contact"];
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/self-service/confirmations": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Confirm (or, for a revocable consent, withdraw) legal requirements of the order for their current document version (S15, LEG-09) */
        post: operations["post_api_self_service_confirmations"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/public/self-service/recover": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /** Request a new self-service link by e-mail (REG-11). Always 202, never reveals whether the address is known. */
        post: operations["post_api_public_self_service_recover"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/health/live": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Liveness: the process answers */
        get: operations["get_api_health_live"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/api/health/ready": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /** Readiness: the database answers */
        get: operations["get_api_health_ready"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
}
export type webhooks = Record<string, never>;
export interface components {
    schemas: {
        AttentionActionRequest: {
            /** @default null */
            note: string | null;
        };
        /** @enum {string} */
        AttentionType: "late_payment" | "overpayment" | "unmatched_payment" | "payment_revoked" | "pairrot_review" | "payment_request_issue_failed" | "integration_degraded" | "integration_inconsistency" | "refund_task" | "consent_withdrawn" | "missing_confirmation" | "cancellation_request" | "delivery_failed" | "outbox_dead_letter" | "scheduled_job_failing" | "capacity_drift";
        /** @enum {string} */
        AttentionState: "open" | "acknowledged" | "resolved" | "dismissed";
        AttentionListQuery: {
            /** @default null */
            type: components["schemas"]["AttentionType"] | (never | null);
            /** @default null */
            state: components["schemas"]["AttentionState"] | (never | null);
            /** @default null */
            eventId: string | null;
            /** @default 1 */
            page: number;
            /** @default 25 */
            perPage: number;
        };
        /** @enum {string} */
        AuditActorKind: "user" | "system" | "participant" | "device" | "pairrot";
        EntityAuditQuery: {
            entityType: string;
            entityId: string;
            /** @default null */
            actor: components["schemas"]["AuditActorKind"] | (never | null);
            /** @default 1 */
            page: number;
            /** @default 50 */
            perPage: number;
        };
        OrganizerAuditQuery: {
            /** @default null */
            entityType: string | null;
            /** @default null */
            actor: components["schemas"]["AuditActorKind"] | (never | null);
            /** @default 1 */
            page: number;
            /** @default 50 */
            perPage: number;
        };
        VenueInput: {
            name: string;
            /** @default null */
            address: string | null;
            /** @default null */
            mapUrl: string | null;
        };
        EventDetailsInput: {
            name: string;
            /** Format: date-time */
            startsAt: string;
            /** Format: date-time */
            endsAt: string;
            /** @default null */
            shortDescription: string | null;
            /** @default null */
            description: string | null;
            /** @default null */
            coverImageUrl: string | null;
            /** @default null */
            contactName: string | null;
            /** @default null */
            contactEmail: string | null;
            /** @default null */
            contactPhone: string | null;
            /** @default Europe/Prague */
            timezone: string;
            /**
             * Format: date-time
             * @default null
             */
            ageReferenceDate: string | null;
            /**
             * @default public
             * @enum {string}
             */
            visibility: "public" | "unlisted";
            /** @default [] */
            venues: components["schemas"]["VenueInput"][];
            /**
             * Format: date-time
             * @default null
             */
            registrationOpensAt: string | null;
            /**
             * Format: date-time
             * @default null
             */
            registrationClosesAt: string | null;
        };
        /** @enum {string} */
        Currency: "CZK" | "EUR";
        Money: {
            minor: number;
            currency: components["schemas"]["Currency"];
            zero: boolean;
            negative: boolean;
            positive: boolean;
        };
        CreateEventInput: {
            details: components["schemas"]["EventDetailsInput"];
            /** @default null */
            slug: string | null;
            /**
             * @default simple
             * @enum {string}
             */
            mode: "simple" | "multi";
            /** @default null */
            price: components["schemas"]["Money"] | (never | null);
            /** @default null */
            capacityLimit: number | null;
        };
        PaymentRulesInput: {
            /** @default [] */
            methods: string[];
            /** @default 7 */
            dueDays: number;
            /** @default 48 */
            holdTtlHours: number;
            /** @default false */
            autoRestoreOnLatePayment: boolean;
            /** @default true */
            selfServiceCancellationAllowed: boolean;
            /**
             * Format: date-time
             * @default null
             */
            selfServiceCancellationDeadline: string | null;
        };
        CancelEventInput: {
            reason: string;
            /** @default false */
            confirmed: boolean;
        };
        RegistrationChangeInput: {
            /** @default null */
            reason: string | null;
            /** @default false */
            cascade: boolean;
        };
        ProgramItemInput: {
            title: string;
            /** Format: date-time */
            startsAt: string;
            /** @default null */
            description: string | null;
            /** @default null */
            location: string | null;
            /** @default null */
            responsible: string | null;
            /**
             * Format: date-time
             * @default null
             */
            endsAt: string | null;
            /** @default null */
            activityId: string | null;
            /**
             * @default public
             * @enum {string}
             */
            visibility: "public" | "participants";
            /** @default false */
            important: boolean;
        };
        NewsPostInput: {
            title: string;
            body: string;
            /** @default 0 */
            priority: number;
            /** @default false */
            broadcast: boolean;
            /** @default false */
            publish: boolean;
        };
        ActivityInput: {
            name: string;
            /** @default null */
            description: string | null;
            /** @default null */
            location: string | null;
            /**
             * Format: date-time
             * @default null
             */
            startsAt: string | null;
            /**
             * Format: date-time
             * @default null
             */
            endsAt: string | null;
            /**
             * Format: date-time
             * @default null
             */
            registrationOpensAt: string | null;
            /**
             * Format: date-time
             * @default null
             */
            registrationClosesAt: string | null;
            /** @default null */
            minAge: number | null;
            /** @default null */
            maxAge: number | null;
            /** @default false */
            minorsOnly: boolean;
            /** @default null */
            capacityLimit: number | null;
            /** @default false */
            important: boolean;
        };
        VariantInput: {
            name: string;
            price: components["schemas"]["Money"];
            /** @default null */
            teamSize: number | null;
            /** @default null */
            minAge: number | null;
            /** @default null */
            maxAge: number | null;
            /** @default null */
            capacityLimit: number | null;
        };
        ChangeCapacityInput: {
            /** @enum {string} */
            scope: "event" | "activity" | "variant";
            /** @default null */
            scopeId: string | null;
            /** @default null */
            limit: number | null;
            /** @default null */
            reason: string | null;
        };
        CheckInRequest: {
            /** @default null */
            stationId: string | null;
            /** @default false */
            acknowledgeWarning: boolean;
        };
        CashCheckInRequest: {
            amountMinor: number;
            /** @default null */
            note: string | null;
            /** @default null */
            stationId: string | null;
        };
        VoidCheckInRequest: {
            reason: string;
        };
        CorrectCheckInRequest: {
            reason: string;
            /** @default null */
            stationId: string | null;
            /**
             * Format: date-time
             * @default null
             */
            checkedInAt: string | null;
        };
        /** @enum {string} */
        CampaignState: "draft" | "scheduled" | "sending" | "sent" | "cancelled";
        /** @enum {string} */
        DeliveryState: "scheduled" | "sent" | "delivered" | "skipped" | "failed";
        ListQuery: {
            /** @default null */
            eventId: string | null;
            /** @default null */
            campaignId: string | null;
            /** @default null */
            orderId: string | null;
            /** @default null */
            campaignState: components["schemas"]["CampaignState"] | (never | null);
            /** @default null */
            state: components["schemas"]["DeliveryState"] | (never | null);
            /** @default 1 */
            page: number;
            /** @default 25 */
            perPage: number;
        };
        /** @enum {string} */
        CampaignPurpose: "organizational" | "marketing";
        /** @enum {string} */
        MessageLocale: "cs" | "en";
        /** @enum {string} */
        TemplateKey: "order_received" | "payment_instructions" | "payment_reminder" | "payment_partial" | "participation_confirmed" | "reservation_expired" | "participation_cancelled" | "participation_changed" | "participation_transferred" | "pre_event_reminder" | "activity_changed" | "program_changed" | "event_cancelled" | "post_event" | "answers_incomplete" | "reconfirmation_request" | "consent_withdrawn" | "refund_settled" | "self_service_link" | "member_invitation" | "news_broadcast" | "document_changed" | "custom";
        AudienceRequest: {
            /** @default [] */
            activityIds: string[];
            /** @default [] */
            variantIds: string[];
            /** @default [] */
            participationStates: string[];
            /** @default [] */
            financialStates: string[];
            /** @default null */
            checkedIn: boolean | null;
        };
        CampaignRequest: {
            eventId: string;
            name: string;
            purpose?: components["schemas"]["CampaignPurpose"];
            locale?: components["schemas"]["MessageLocale"];
            /** @default null */
            templateKey: components["schemas"]["TemplateKey"] | (never | null);
            /** @default null */
            subject: string | null;
            /** @default null */
            body: string | null;
            audience?: components["schemas"]["AudienceRequest"];
        };
        CampaignUpdateRequest: {
            name: string;
            purpose?: components["schemas"]["CampaignPurpose"];
            locale?: components["schemas"]["MessageLocale"];
            /** @default null */
            templateKey: components["schemas"]["TemplateKey"] | (never | null);
            /** @default null */
            subject: string | null;
            /** @default null */
            body: string | null;
            audience?: components["schemas"]["AudienceRequest"];
        };
        ScheduleCampaignRequest: {
            /** Format: date-time */
            at: string;
        };
        TemplatePreviewRequest: {
            /** @default null */
            key: components["schemas"]["TemplateKey"] | (never | null);
            locale?: components["schemas"]["MessageLocale"];
            /** @default null */
            subject: string | null;
            /** @default null */
            body: string | null;
        };
        TemplateRequest: {
            key: components["schemas"]["TemplateKey"];
            locale: components["schemas"]["MessageLocale"];
            subject: string;
            body: string;
        };
        TemplateTextRequest: {
            subject: string;
            body: string;
        };
        ReminderSettingsRequest: {
            paymentOffsetsHours: number[];
            preEventHours: number;
        };
        /** @enum {string} */
        SuppressionScope: "marketing" | "all";
        SuppressionRequest: {
            email: string;
            scope?: components["schemas"]["SuppressionScope"];
        };
        CreateCollectionRequest: {
            title: string;
            /**
             * `participants` (registered only, through self-service) is P1.
             * @default public
             * @enum {string}
             */
            visibility: "public" | "link_only";
            /**
             * A photographer's gallery (POST-02); without it the collection is an internal gallery for uploads.
             * @default null
             */
            externalUrl: string | null;
        };
        ArchiveRequest: {
            /** @default null */
            inArchive: boolean | null;
        };
        AddMaterialRequest: {
            title: string;
            url: string;
            /**
             * @default other
             * @enum {string}
             */
            type: "results" | "video" | "document" | "partners" | "other";
            /** @default 0 */
            position: number;
        };
        IssueDeviceTokenRequest: {
            /** @default  */
            label: string;
            /** @default null */
            checkInListId: string | null;
            /**
             * Format: date-time
             * @default null
             */
            expiresAt: string | null;
        };
        InvitationTokenRequest: {
            /** @default  */
            token: string;
            /** @default null */
            password: string | null;
            /** @default null */
            displayName: string | null;
        };
        ProfileRequest: {
            /** @default  */
            name: string;
            /** @default null */
            contactEmail: string | null;
            /** @default null */
            contactPhone: string | null;
            /** @default null */
            legalName: string | null;
            /** @default null */
            companyId: string | null;
            /** @default null */
            vatId: string | null;
            /** @default null */
            billingStreet: string | null;
            /** @default null */
            billingCity: string | null;
            /** @default null */
            billingPostalCode: string | null;
            /** @default null */
            billingCountry: string | null;
            /** @default null */
            logoUrl: string | null;
            /** @default null */
            coverImageUrl: string | null;
            /** @default null */
            brandColor: string | null;
        };
        /** @enum {string} */
        OverpaymentHandling: "refund" | "donation" | "ask";
        PaymentSettingsRequest: {
            /** @default 14 */
            defaultDueDays: number;
            /** @default 48 */
            defaultHoldTtlHours: number;
            /** @default false */
            autoRestoreLatePayment: boolean;
            overpaymentHandling?: components["schemas"]["OverpaymentHandling"];
            /** @default false */
            checkInMayRecordCash: boolean;
            /** @default false */
            eventManagerMayResolvePayments: boolean;
        };
        ConnectPairrotRequest: {
            /** @default  */
            accountNumberOrIban: string;
            /** @default Eventor */
            label: string;
        };
        SignUpRequest: {
            /** @default  */
            email: string;
            /** @default  */
            password: string;
            /** @default  */
            displayName: string;
            /** @default  */
            organizerName: string;
            /** @default  */
            slug: string;
        };
        ChangeMemberRequest: {
            /**
             * @default
             * @enum {string}
             */
            role: "OWNER" | "EVENT_MANAGER" | "FINANCE" | "CHECK_IN";
            /** @default [] */
            eventScope: string[];
        };
        InviteMemberRequest: {
            /** @default  */
            email: string;
            /**
             * @default
             * @enum {string}
             */
            role: "OWNER" | "EVENT_MANAGER" | "FINANCE" | "CHECK_IN";
            /** @default [] */
            eventScope: string[];
        };
        /** @enum {string} */
        DocumentLevel: "organizer" | "event" | "activity";
        CreateDocumentRequest: {
            level: components["schemas"]["DocumentLevel"];
            title: string;
            /**
             * Event or activity id; omitted for an organizer-level document.
             * @default null
             */
            scopeId: string | null;
            /** @default null */
            replacesDocumentId: string | null;
        };
        DraftVersionRequest: {
            /** @enum {string} */
            kind: "text" | "pdf" | "external_link";
            /** @default null */
            text: string | null;
            /** @default null */
            pdfBase64: string | null;
            /** @default null */
            url: string | null;
        };
        PublishVersionRequest: {
            /** @default false */
            requiresReconfirmation: boolean;
        };
        /** @enum {string} */
        RequirementType: "terms" | "privacy_notice" | "health_declaration" | "consent" | "likeness";
        /** @enum {string} */
        Confirmer: "orderer" | "each_participant" | "guardian";
        DefineRequirement: {
            documentId: string;
            type: components["schemas"]["RequirementType"];
            purpose: string;
            confirmer?: components["schemas"]["Confirmer"];
            /** @default false */
            mandatory: boolean;
            /** @default null */
            revocable: boolean | null;
            /** @default false */
            preChecked: boolean;
            /** @default null */
            scopeText: string | null;
            /** @default null */
            validity: string | null;
            /** @default null */
            activityId: string | null;
            /** @default null */
            variantId: string | null;
        };
        ChangeRequirementRequest: {
            purpose: string;
            confirmer?: components["schemas"]["Confirmer"];
            /** @default false */
            mandatory: boolean;
            /** @default null */
            revocable: boolean | null;
            /** @default false */
            preChecked: boolean;
            /** @default null */
            scopeText: string | null;
            /** @default null */
            validity: string | null;
            /** @default null */
            activityId: string | null;
            /** @default null */
            variantId: string | null;
        };
        AllocateRequest: {
            orderId: string;
            reason: string;
            /** @default null */
            paymentRequestId: string | null;
        };
        ReasonRequest: {
            reason: string;
        };
        CorrectCashRequest: {
            reason: string;
            /** @default null */
            replacementAmountMinor: number | null;
        };
        OpenRefundRequest: {
            orderId: string;
            amountMinor: number;
            /** @enum {string} */
            reason: "cancellation" | "overpayment" | "event_cancelled" | "late_payment";
            /** @default null */
            note: string | null;
        };
        RecordCashRequest: {
            amountMinor: number;
            /** @default null */
            note: string | null;
        };
        ManualPaymentRequest: {
            amountMinor: number;
            reason: string;
        };
        SettleRefundRequest: {
            method: string;
            /** @default null */
            reference: string | null;
            /** @default null */
            note: string | null;
        };
    };
    responses: never;
    parameters: never;
    requestBodies: never;
    headers: never;
    pathItems: never;
}
export type $defs = Record<string, never>;
export interface operations {
    get_api_admin_attention_summary: {
        parameters: {
            query?: {
                eventId?: string;
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description unresolved total and byType */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: unknown organizer or not a member */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed: malformed event id */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_attention_action: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                itemId: string;
                action: string;
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": {
                    note?: string | null;
                };
            };
        };
        responses: {
            /** @description The item after the action, incl. its remaining actions */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found (organizer) or attention_item_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description attention_action_not_available or validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_attention_detail: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                itemId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The item incl. actions [{key, kind: command|link, noteAllowed, target}] */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found (organizer) or attention_item_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_attention_list: {
        parameters: {
            query?: {
                type?: string;
                state?: "open" | "acknowledged" | "resolved" | "dismissed";
                eventId?: string;
                page?: number;
                perPage?: number;
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description A page: items, page, perPage, total */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks payment.exception.assign */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: unknown organizer or not a member */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed: unknown type or state, bad event id, page out of range */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_audit_entity: {
        parameters: {
            query: {
                entityType: string;
                entityId: string;
                actor?: "user" | "system" | "participant" | "device" | "pairrot";
                page?: number;
                perPage?: number;
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description A page: items, page, perPage, total */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role may not see audit entries of this entity type */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: unknown organizer or not a member */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed: entityType or entityId missing, unknown actor */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_audit_log: {
        parameters: {
            query?: {
                entityType?: string;
                actor?: "user" | "system" | "participant" | "device" | "pairrot";
                page?: number;
                perPage?: number;
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description A page: items, page, perPage, total */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: only audit.view.all (owner) */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: unknown organizer or not a member */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed: unknown actor, page out of range */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_event_list: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description List of events */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: unknown organizer or not a member */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_event_create: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["CreateEventInput"];
            };
        };
        responses: {
            /** @description Created: eventId, slug, activityId and variantId of the simple mode */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.edit */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: unknown organizer or not a member */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description event_slug_taken */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / invalid_catalog_data */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_event_detail: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The event; `version` goes into If-Match of the next write */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_event_update: {
        parameters: {
            query?: never;
            header?: {
                "If-Match"?: number;
            };
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["EventDetailsInput"];
            };
        };
        responses: {
            /** @description The updated event */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.edit */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description version_conflict */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / invalid_catalog_data */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_event_payment_rules: {
        parameters: {
            query?: never;
            header?: {
                "If-Match"?: number;
            };
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["PaymentRulesInput"];
            };
        };
        responses: {
            /** @description The updated event */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.configure */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description version_conflict */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_event_publish: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Published: formVersionId and formVersion */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.configure */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description transition_not_allowed: not a draft */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description event_not_publishable: `violations` lists every broken rule */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_event_cancel: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["CancelEventInput"];
            };
        };
        responses: {
            /** @description The cancelled event */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.cancel */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description transition_not_allowed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed: reason and confirmation are required */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_event_complete: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The completed event */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.configure */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description transition_not_allowed: not published, or not over yet */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_event_archive: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The archived event */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.configure */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description transition_not_allowed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_event_registration: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                transition: "open" | "pause" | "resume" | "close";
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": components["schemas"]["RegistrationChangeInput"] | (never | null);
            };
        };
        responses: {
            /** @description The event with the new registration state */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.configure */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description transition_not_allowed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_activity_registration: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                activityId: string;
                transition: "open" | "pause" | "resume" | "close";
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": components["schemas"]["RegistrationChangeInput"] | (never | null);
            };
        };
        responses: {
            /** @description The event with the new state of the activity */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.configure */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / activity_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description transition_not_allowed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_form_overview: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description draft, published, versions */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_form_version: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                number: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The version with its fields */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / form_version_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_form_edit_draft: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    fields: Record<string, never>[];
                };
            };
        };
        responses: {
            /** @description The draft as saved (overview) */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.edit */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description form_schema_invalid: `violations` lists every problem */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_form_publish: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description formVersionId and number */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.edit */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invalid_catalog_data (no draft, event not published) / event_not_publishable */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_form_field_types: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description types and semanticFields */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: unknown organizer or not a member */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_program_list: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description items */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_program_add: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ProgramItemInput"];
            };
        };
        responses: {
            /** @description programItemId and the program */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks program.manage */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / activity_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / invalid_catalog_data */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_program_update: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                itemId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ProgramItemInput"];
            };
        };
        responses: {
            /** @description The program */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks program.manage */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / program_item_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / invalid_catalog_data */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    delete_api_admin_program_remove: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                itemId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The program */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks program.manage */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / program_item_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_news_list: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description items */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_news_create: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["NewsPostInput"];
            };
        };
        responses: {
            /** @description newsPostId, published and the posts */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks program.manage */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_news_update: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                postId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["NewsPostInput"];
            };
        };
        responses: {
            /** @description The posts */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks program.manage */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / news_post_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description news_post_already_published */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_news_publish: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                postId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The posts */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks program.manage */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / news_post_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description news_post_already_published */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_public_event_list: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description items */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: unknown organizer */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_public_event_detail: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The public event page */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found: unknown organizer, unknown event or a draft */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_public_event_form: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description formVersionId, number, schemaSha256 and fields */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_public_event_availability: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description eventId, shown, event, activities and variants (keyed by id) */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_activity_add: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ActivityInput"];
            };
        };
        responses: {
            /** @description The event with the new activity; `activityId` in the Location-less body is the new id */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.edit */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / invalid_catalog_data */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_activity_update: {
        parameters: {
            query?: never;
            header?: {
                "If-Match"?: number;
            };
            path: {
                orgSlug: string;
                eventId: string;
                activityId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ActivityInput"];
            };
        };
        responses: {
            /** @description The updated event */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.edit */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / activity_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description version_conflict */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / invalid_catalog_data */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    delete_api_admin_activity_remove: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                activityId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The updated event */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.edit */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / activity_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description event_not_draft */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_variant_add: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                activityId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["VariantInput"];
            };
        };
        responses: {
            /** @description variantId and the updated event */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.edit */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / activity_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / invalid_catalog_data */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_variant_update: {
        parameters: {
            query?: never;
            header?: {
                "If-Match"?: number;
            };
            path: {
                orgSlug: string;
                eventId: string;
                variantId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["VariantInput"];
            };
        };
        responses: {
            /** @description The updated event */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.edit */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / variant_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description version_conflict */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / invalid_catalog_data */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    delete_api_admin_variant_remove: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                variantId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The updated event */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.edit */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / variant_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description event_not_draft */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_event_capacity: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Buckets ordered event, activity, variant, wave. `limit` and `available` are null for unlimited; `overbooked` after a limit was lowered below the occupancy (CAP-06). */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": {
                        /** Format: uuid */
                        eventId: string;
                        buckets: {
                            /** Format: uuid */
                            bucketId: string;
                            /** @enum {string} */
                            scope: "event" | "activity" | "variant" | "wave";
                            /** Format: uuid */
                            scopeId: string;
                            /** @enum {string} */
                            unit: "seat" | "team";
                            limit: number | null;
                            held: number;
                            confirmed: number;
                            available: number | null;
                            overbooked: boolean;
                        }[];
                    };
                };
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.configure */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found (unknown organizer or not a member) or capacity_bucket_not_found (event unknown or without buckets) */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_capacity_change: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ChangeCapacityInput"];
            };
        };
        responses: {
            /** @description belowOccupancy, warnings (`capacity_below_occupancy`) and the updated event */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks event.configure */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found / activity_not_found / variant_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / invalid_catalog_data */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_check_in_record: {
        parameters: {
            query?: never;
            header: {
                "Idempotency-Key": string;
            };
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
                listId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description qr1.<payload>.<sig> */
                    qr?: string;
                    /** @description Registration code */
                    code?: string;
                    /** Format: uuid */
                    participationId?: string;
                    /** Format: uuid */
                    stationId?: string | null;
                    /** @default false */
                    acknowledgeWarning?: boolean;
                };
            };
        };
        responses: {
            /** @description {outcome: checked_in, checkInId, checkedInAt, situation} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description {outcome: escalated, situation}: the situation was passed on, the participant is not checked in */
            202: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description idempotency_key_required */
            400: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden (also a device token bound to another list) */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / participation_not_found / check_in_list_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description already_checked_in (checkedInAt, checkedInBy, stationId, deviceLabel) / check_in_blocked / check_in_warning_unacknowledged / check_in_list_closed / idempotency_key_reused */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / qr_token_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_check_in_record_device: {
        parameters: {
            query?: never;
            header: {
                "Idempotency-Key": string;
            };
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
                listId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description qr1.<payload>.<sig> */
                    qr?: string;
                    /** @description Registration code */
                    code?: string;
                    /** Format: uuid */
                    participationId?: string;
                    /** Format: uuid */
                    stationId?: string | null;
                    /** @default false */
                    acknowledgeWarning?: boolean;
                };
            };
        };
        responses: {
            /** @description {outcome: checked_in, checkInId, checkedInAt, situation} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description {outcome: escalated, situation}: the situation was passed on, the participant is not checked in */
            202: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description idempotency_key_required */
            400: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden (also a device token bound to another list) */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / participation_not_found / check_in_list_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description already_checked_in (checkedInAt, checkedInBy, stationId, deviceLabel) / check_in_blocked / check_in_warning_unacknowledged / check_in_list_closed / idempotency_key_reused */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / qr_token_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_check_in_cash: {
        parameters: {
            query?: never;
            header: {
                "Idempotency-Key": string;
            };
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
                listId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    qr?: string;
                    code?: string;
                    /** Format: uuid */
                    participationId?: string;
                    /** @description Amount in haléře */
                    amountMinor: number;
                    note?: string | null;
                    /** Format: uuid */
                    stationId?: string | null;
                };
            };
        };
        responses: {
            /** @description {checkInId, checkedInAt, ledgerEntryId, alreadyRecorded, financialState, received, amountDue} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description idempotency_key_required */
            400: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden / cash_not_allowed */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / participation_not_found / check_in_list_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description already_checked_in / check_in_blocked / cash_check_in_not_applicable / check_in_list_closed / idempotency_key_reused */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / qr_token_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_check_in_cash_device: {
        parameters: {
            query?: never;
            header: {
                "Idempotency-Key": string;
            };
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
                listId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    qr?: string;
                    code?: string;
                    /** Format: uuid */
                    participationId?: string;
                    /** @description Amount in haléře */
                    amountMinor: number;
                    note?: string | null;
                    /** Format: uuid */
                    stationId?: string | null;
                };
            };
        };
        responses: {
            /** @description {checkInId, checkedInAt, ledgerEntryId, alreadyRecorded, financialState, received, amountDue} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description idempotency_key_required */
            400: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden / cash_not_allowed */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / participation_not_found / check_in_list_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description already_checked_in / check_in_blocked / cash_check_in_not_applicable / check_in_list_closed / idempotency_key_reused */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / qr_token_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_check_in_escalate: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
                listId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    qr?: string;
                    code?: string;
                    /** Format: uuid */
                    participationId?: string;
                };
            };
        };
        responses: {
            /** @description {situation} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / participation_not_found / check_in_list_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description nothing_to_escalate / check_in_list_closed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / qr_token_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_check_in_escalate_device: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
                listId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    qr?: string;
                    code?: string;
                    /** Format: uuid */
                    participationId?: string;
                };
            };
        };
        responses: {
            /** @description {situation} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / participation_not_found / check_in_list_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description nothing_to_escalate / check_in_list_closed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / qr_token_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_check_in_void: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                checkInId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    reason: string;
                };
            };
        };
        responses: {
            /** @description Voided */
            204: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / check_in_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description check_in_already_voided */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_check_in_correct: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                checkInId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    reason: string;
                    /** Format: uuid */
                    stationId?: string | null;
                    /** Format: date-time */
                    checkedInAt?: string | null;
                };
            };
        };
        responses: {
            /** @description {checkInId, replacedCheckInId} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / check_in_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description check_in_already_voided */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_check_in_lists: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [{id, eventId, activityId, name, opensAt, expected, checkedIn}]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_check_in_lists_device: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [{id, eventId, activityId, name, opensAt, expected, checkedIn}]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_check_in_overview: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {eventId, lists: [{listId, activityId, name, opensAt, expected, checkedIn, missing, unpaid, cashReceivedMinor}], expected, checkedIn, missing, unpaid, cashReceivedMinor} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_check_in_overview_device: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {eventId, lists: [{listId, activityId, name, opensAt, expected, checkedIn, missing, unpaid, cashReceivedMinor}], expected, checkedIn, missing, unpaid, cashReceivedMinor} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_check_in_search: {
        parameters: {
            query: {
                /** @description At least two characters */
                q: string;
            };
            header?: never;
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
                listId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [{participationId, displayName, registrationCode, activityId, state, checkedInAt}]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / check_in_list_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description check_in_list_closed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_check_in_search_device: {
        parameters: {
            query: {
                /** @description At least two characters */
                q: string;
            };
            header?: never;
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
                listId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [{participationId, displayName, registrationCode, activityId, state, checkedInAt}]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / check_in_list_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description check_in_list_closed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_check_in_lookup: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
                listId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description qr1.<payload>.<sig> */
                    qr?: string;
                    /** @description Registration code */
                    code?: string;
                    /** Format: uuid */
                    participationId?: string;
                };
            };
        };
        responses: {
            /** @description The participant with `decision` (allow | warn | escalate | block), `situation`, `checkIn` (when already checked in) and `cash` (the cash offer of an unpaid participant) */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / participation_not_found / check_in_list_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description check_in_list_closed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / qr_token_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_check_in_lookup_device: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /** @description Team members only; a device token needs none */
                orgSlug: string;
                eventId: string;
                listId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description qr1.<payload>.<sig> */
                    qr?: string;
                    /** @description Registration code */
                    code?: string;
                    /** Format: uuid */
                    participationId?: string;
                };
            };
        };
        responses: {
            /** @description The participant with `decision` (allow | warn | escalate | block), `situation`, `checkIn` (when already checked in) and `cash` (the cash offer of an unpaid participant) */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / participation_not_found / check_in_list_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description check_in_list_closed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed / qr_token_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_campaign_list: {
        parameters: {
            query?: {
                eventId?: string | null;
                campaignId?: string | null;
                orderId?: string | null;
                campaignState?: components["schemas"]["CampaignState"] | (never | null);
                state?: components["schemas"]["DeliveryState"] | (never | null);
                page?: number;
                perPage?: number;
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items, page, perPage, total} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_campaign_create: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["CampaignRequest"];
            };
        };
        responses: {
            /** @description {campaignId} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed, invalid_campaign or template_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_campaign_preview: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["CampaignRequest"];
            };
        };
        responses: {
            /** @description {subject, body, recipientCount} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed, invalid_campaign or template_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_campaign_get: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                campaignId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The campaign */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or campaign_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_campaign_update: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                campaignId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["CampaignUpdateRequest"];
            };
        };
        responses: {
            /** @description The campaign after the change */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or campaign_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description campaign_state_invalid: only a draft can be edited */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed, invalid_campaign or template_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_campaign_send: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                campaignId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {recipients} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or campaign_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description campaign_state_invalid */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_campaign_schedule: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                campaignId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ScheduleCampaignRequest"];
            };
        };
        responses: {
            /** @description The campaign */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or campaign_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description campaign_state_invalid */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or invalid_campaign (not in the future) */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_campaign_cancel: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                campaignId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The campaign */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or campaign_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description campaign_state_invalid */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_delivery_list: {
        parameters: {
            query?: {
                eventId?: string | null;
                campaignId?: string | null;
                orderId?: string | null;
                campaignState?: components["schemas"]["CampaignState"] | (never | null);
                state?: components["schemas"]["DeliveryState"] | (never | null);
                page?: number;
                perPage?: number;
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items, page, perPage, total} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_campaign_deliveries: {
        parameters: {
            query?: {
                eventId?: string | null;
                campaignId?: string | null;
                orderId?: string | null;
                campaignState?: components["schemas"]["CampaignState"] | (never | null);
                state?: components["schemas"]["DeliveryState"] | (never | null);
                page?: number;
                perPage?: number;
            };
            header?: never;
            path: {
                orgSlug: string;
                campaignId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items, page, perPage, total} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or campaign_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_campaign_retry: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                campaignId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {retried} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or campaign_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_delivery_get: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                deliveryId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The delivery */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or delivery_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_delivery_retry: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                deliveryId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {retried} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or delivery_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description delivery_not_retryable: only a failed delivery can be retried */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_template_list: {
        parameters: {
            query?: {
                key?: string;
                locale?: "cs" | "en";
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [{id|null, key, locale, subject, body, source: custom|built_in}]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: unknown organizer or not a member */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_template_create: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["TemplateRequest"];
            };
        };
        responses: {
            /** @description {templateId} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description template_exists */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or template_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_template_variables: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {variables: {name: meaning}} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_template_preview: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["TemplatePreviewRequest"];
            };
        };
        responses: {
            /** @description {subject, body} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or template_invalid (unknown variable, forbidden tag) */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_template_get: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                templateId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The template */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or message_template_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_template_update: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                templateId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["TemplateTextRequest"];
            };
        };
        responses: {
            /** @description The template after the change */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or message_template_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or template_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    delete_api_admin_template_delete: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                templateId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Removed */
            204: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or message_template_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_reminder_settings_get: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {paymentOffsetsHours, preEventHours, custom} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_reminder_settings_put: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ReminderSettingsRequest"];
            };
        };
        responses: {
            /** @description The schedule after the change */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or invalid_reminder_settings */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_suppression_list: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_suppression_add: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["SuppressionRequest"];
            };
        };
        responses: {
            /** @description {suppressionId} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    delete_api_admin_suppression_lift: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                suppressionId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Removed */
            204: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or suppression_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_content_collections: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [...]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_content_collection_create: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["CreateCollectionRequest"];
            };
        };
        responses: {
            /** @description {collectionId, linkToken} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or event_not_found (unknown or draft event) */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_content_photos: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                collectionId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [...]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or collection_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_content_photo_upload: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                collectionId: string;
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "multipart/form-data": {
                    /** Format: binary */
                    photo: string;
                    description?: string;
                    author?: string;
                    eventPart?: string;
                    position?: number;
                };
            };
        };
        responses: {
            /** @description {photoId} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or collection_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description collection_is_external */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invalid_photo */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_content_collection_publish: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                collectionId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {collectionId, published} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or collection_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_content_collection_unpublish: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                collectionId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {collectionId, published} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or collection_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_content_collection_archive: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                collectionId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ArchiveRequest"];
            };
        };
        responses: {
            /** @description {collectionId, published} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or collection_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_content_materials: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [...]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_content_material_add: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["AddMaterialRequest"];
            };
        };
        responses: {
            /** @description {materialId} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_content_material_publish: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                materialId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {materialId} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or material_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_content_material_unpublish: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                materialId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {materialId} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or material_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_content_material_archive: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
                materialId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ArchiveRequest"];
            };
        };
        responses: {
            /** @description {materialId} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or material_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_content_closing_message: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {campaignId} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_public_content: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {collections: [...], materials: [...]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_public_content_archive: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {collections: [...], materials: [...]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or event_not_found (unknown or not finished) */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_public_content_photos: {
        parameters: {
            query?: {
                limit?: number;
                offset?: number;
            };
            header?: never;
            path: {
                orgSlug: string;
                eventSlug: string;
                collectionId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [...]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description collection_not_found or event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_public_content_link: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventSlug: string;
                token: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {collectionId, items: [...]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description collection_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_public_content_photo_file: {
        parameters: {
            query?: {
                token?: string;
            };
            header?: never;
            path: {
                photoId: string;
                variant: "original" | "thumbnail";
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description image/jpeg, image/png or image/webp */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description photo_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_device_tokens: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description items: id, label, checkInListId, createdAt, expiresAt, revokedAt, active */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_device_token_issue: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @example Gate A tablet */
                    label: string;
                    /** Format: uuid */
                    checkInListId?: string | null;
                    /** Format: date-time */
                    expiresAt?: string | null;
                };
            };
        };
        responses: {
            /** @description id and the token `dev1_...` (send it as `Authorization: Bearer`) */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or device_token_expiry_in_past */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    delete_api_admin_device_token_revoke: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                deviceTokenId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Revoked */
            204: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / device_token_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_auth_invitation_preview: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    token: string;
                };
            };
        };
        responses: {
            /** @description organizerName, organizerSlug, email, role, accountExists, expiresAt */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invitation_not_found: unknown, expired, revoked or used */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description rate_limited */
            429: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_auth_invitation_accept: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    token: string;
                    /** Format: password */
                    password?: string;
                    displayName?: string;
                };
            };
        };
        responses: {
            /** @description organizerSlug, accountCreated and the profile of the signed-in account */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invitation_email_mismatch or csrf_invalid */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invitation_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description sign_in_required or already_member */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or account_details_required */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description rate_limited */
            429: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_auth_login: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** Format: email */
                    email: string;
                    /** Format: password */
                    password: string;
                };
            };
        };
        responses: {
            /** @description Signed in; body is the profile (as GET /api/auth/me). Sets the EVENTOR_SESSID cookie. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invalid_credentials */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description csrf_invalid */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description rate_limited */
            429: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_auth_me: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Profile with memberships */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_organizer_detail: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Organizer detail incl. your role */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: the role lacks organizer.view */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: unknown organizer or not a member */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_organizer_profile: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The profile */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: unknown organizer or not a member */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_organizer_profile_update: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    name: string;
                    /**
                     * Format: email
                     * @description Reply-To of messages to participants
                     */
                    contactEmail?: string | null;
                    contactPhone?: string | null;
                    legalName?: string | null;
                    /** @description Business identification number (ICO) */
                    companyId?: string | null;
                    vatId?: string | null;
                    billingStreet?: string | null;
                    billingCity?: string | null;
                    billingPostalCode?: string | null;
                    /** @example CZ */
                    billingCountry?: string | null;
                    logoUrl?: string | null;
                    coverImageUrl?: string | null;
                    /** @example #1a73e8 */
                    brandColor?: string | null;
                };
            };
        };
        responses: {
            /** @description The saved profile */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: only the owner may edit the profile */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_payment_settings: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The payment settings */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: owner only */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_payment_settings_update: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @example 14 */
                    defaultDueDays?: number;
                    /** @example 48 */
                    defaultHoldTtlHours?: number;
                    autoRestoreLatePayment?: boolean;
                    /** @enum {string} */
                    overpaymentHandling?: "refund" | "donation" | "ask";
                    checkInMayRecordCash?: boolean;
                    eventManagerMayResolvePayments?: boolean;
                };
            };
        };
        responses: {
            /** @description The saved settings */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: owner only */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_payment_settings_pairrot: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    accountNumberOrIban: string;
                    label?: string;
                };
            };
        };
        responses: {
            /** @description Connected: the payment settings plus the Pairrot inbound address */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: owner only */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description bank_account_unavailable: connected elsewhere */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or contact_email_required */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description pairrot_unavailable */
            503: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_auth_sign_up: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** Format: email */
                    email: string;
                    /** Format: password */
                    password: string;
                    displayName: string;
                    organizerName: string;
                    /** @example klub-plzen */
                    slug: string;
                };
            };
        };
        responses: {
            /** @description Registered and signed in; body is the profile (as GET /api/auth/me). Sets the EVENTOR_SESSID cookie. */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description csrf_invalid */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description email_already_registered or organizer_slug_taken */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description rate_limited */
            429: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_team_members: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description items: membershipId, userAccountId, email, displayName, role, eventScope, joinedAt */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: owner only */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_team_member_change: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                membershipId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @enum {string} */
                    role: "OWNER" | "EVENT_MANAGER" | "FINANCE" | "CHECK_IN";
                    /** @description Empty = all events; only EVENT_MANAGER and CHECK_IN may be restricted */
                    eventScope?: string[];
                };
            };
        };
        responses: {
            /** @description The updated member list */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / membership_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description last_owner_required */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or invalid_event_scope */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    delete_api_admin_team_member_remove: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                membershipId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Removed */
            204: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / membership_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description last_owner_required */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_team_invitations: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description items: invitationId, email, role, eventScope, invitedAt, expiresAt */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_team_invite: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** Format: email */
                    email: string;
                    /** @enum {string} */
                    role: "OWNER" | "EVENT_MANAGER" | "FINANCE" | "CHECK_IN";
                    eventScope?: string[];
                };
            };
        };
        responses: {
            /** @description invitationId, expiresAt (and `acceptToken` on development and test stacks only) */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description already_member */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or invalid_event_scope */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    delete_api_admin_team_invitation_revoke: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                invitationId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Revoked */
            204: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / invitation_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_check_in_whoami: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description `type` is `device` (deviceTokenId, organizerId, checkInListId, label) or `member` (the profile) */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized / invalid_device_token */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_legal_confirmation_list: {
        parameters: {
            query?: {
                requirementId?: string;
                participationId?: string;
                orderId?: string;
                decision?: "requested" | "granted" | "declined" | "withdrawn";
                /** @description Only the current decision of every chain */
                current?: boolean;
                limit?: number;
                offset?: number;
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [...], total} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_legal_document_list: {
        parameters: {
            query?: {
                level?: "organizer" | "event" | "activity";
                scopeId?: string;
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [...]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_legal_document_create: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["CreateDocumentRequest"];
            };
        };
        responses: {
            /** @description Created: {documentId} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: organizer, or the replaced document */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or invalid_legal_document */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_legal_document_get: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                documentId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Document with versions */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or legal_document_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_legal_version_draft: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                documentId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["DraftVersionRequest"];
            };
        };
        responses: {
            /** @description Draft stored: {versionId, number, sha256} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or legal_document_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or invalid_legal_document */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_legal_version_get: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                documentId: string;
                versionId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Version */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or legal_document_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_legal_version_publish: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                documentId: string;
                versionId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["PublishVersionRequest"];
            };
        };
        responses: {
            /** @description Published: {versionId, number, requiresReconfirmation} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or legal_document_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description document_version_already_published */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_public_legal_version: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                versionId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Version */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found: unknown organizer, unknown or unpublished version */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_public_legal_version_content: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                versionId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The content with its media type */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_public_legal_content_by_hash: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                sha256: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The content with its media type */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_legal_requirement_list: {
        parameters: {
            query?: {
                documentId?: string;
                includeArchived?: boolean;
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [...]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_legal_requirement_define: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["DefineRequirement"];
            };
        };
        responses: {
            /** @description Created: {requirementId} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or legal_document_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed (incl. consent_not_mandatory, consent_not_prechecked) */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_admin_legal_requirement_change: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                requirementId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ChangeRequirementRequest"];
            };
        };
        responses: {
            /** @description Changed */
            204: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or legal_requirement_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed or invalid_requirement */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    delete_api_admin_legal_requirement_archive: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                requirementId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Archived */
            204: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found or legal_requirement_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_legal_preview: {
        parameters: {
            query: {
                eventId: string;
                activityId?: string;
                variantId?: string;
                /** @description Preview for a child (guardian requirements apply) */
                minor?: boolean;
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {orderLevel: [...], perSlot: [...]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_payments_allocate_unmatched: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                transactionId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** Format: uuid */
                    orderId: string;
                    reason: string;
                    /** Format: uuid */
                    paymentRequestId?: string | null;
                };
            };
        };
        responses: {
            /** @description {paymentRequestId}: allocation requested */
            202: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / unmatched_transaction_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description allocation_not_possible */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description pairrot_unavailable */
            503: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_payments_cancel_refund_task: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                taskId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    reason: string;
                };
            };
        };
        responses: {
            /** @description {refundTaskId, state} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / refund_task_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description refund_task_not_open */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_payments_correct_cash: {
        parameters: {
            query?: never;
            header: {
                "Idempotency-Key": string;
            };
            path: {
                orgSlug: string;
                orderId: string;
                entryId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    reason: string;
                    replacementAmountMinor?: number | null;
                };
            };
        };
        responses: {
            /** @description {correctionId, replacementId, alreadyRecorded, state, received, amountDue} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / ledger_entry_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description ledger_entry_not_correctable */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_payments_refund_tasks: {
        parameters: {
            query?: {
                status?: "open" | "settled" | "cancelled";
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [{id, orderId, amount, reason, status, note, createdAt, settledAt, settleMethod, settleReference}]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed: unknown status */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_payments_open_refund_task: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** Format: uuid */
                    orderId: string;
                    amountMinor: number;
                    /** @enum {string} */
                    reason: "cancellation" | "overpayment" | "event_cancelled" | "late_payment";
                    note?: string | null;
                };
            };
        };
        responses: {
            /** @description {refundTaskId} */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / order_finance_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description refund_task_not_open: the order already has an open task */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_payments_order_finance: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                orderId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description state, amountDue, received (transfer/cash), refunded, outstanding, paymentRequests[], openRefundTask */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / order_finance_not_found (also for an order of another organizer) */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_payments_order_ledger: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                orderId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [{id, kind, amount, source, compensatesId, actor, reason, occurredAt, recordedAt}]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / order_finance_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_payments_connection: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {connected, subtenantId, bankConnectionId, maskedIban, inboundAddress, receptionState (null when Pairrot cannot be reached), pairrotReachable} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_webhooks_pairrot: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The event is stored (status "accepted") or was already known (status "duplicate") */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invalid_webhook: signed but not a Pairrot event */
            400: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invalid_signature: wrong key, wrong body or a timestamp outside +-5 minutes */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_payments_record_cash: {
        parameters: {
            query?: never;
            header: {
                "Idempotency-Key": string;
            };
            path: {
                orgSlug: string;
                orderId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description Amount in haléře */
                    amountMinor: number;
                    note?: string | null;
                };
            };
        };
        responses: {
            /** @description {ledgerEntryId, alreadyRecorded, state, received, amountDue} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description idempotency_key_required */
            400: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / order_finance_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description idempotency_key_reused */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_payments_manual_payment: {
        parameters: {
            query?: never;
            header: {
                "Idempotency-Key": string;
            };
            path: {
                orgSlug: string;
                orderId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @description Signed amount in haléře, not zero */
                    amountMinor: number;
                    reason: string;
                };
            };
        };
        responses: {
            /** @description {ledgerEntryId, alreadyRecorded, state, received, amountDue} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / order_finance_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_payments_retry_request: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                paymentRequestId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {paymentRequestId}: the request was created in Pairrot */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / payment_request_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description payment_request_state: the request has not failed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description pairrot_unavailable: it stays PENDING and the outbox-style retry is manual */
            503: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_payments_settle_refund_task: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                taskId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** @example bank_transfer */
                    method: string;
                    reference?: string | null;
                    note?: string | null;
                };
            };
        };
        responses: {
            /** @description {refundTaskId, ledgerEntryId, state} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden: EVENT_MANAGER may create but not close */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / refund_task_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description refund_task_not_open */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description validation_failed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_payments_unmatched_transactions: {
        parameters: {
            query?: {
                state?: "open" | "all";
            };
            header?: never;
            path: {
                orgSlug: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [{id, amount, variableSymbol, bookedOn, matchingResult, resolvedAt, resolution}]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_payments_unmatched_candidates: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                transactionId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description {items: [{paymentRequestId, orderId, variableSymbol, amount, outstanding, reasons[]}]} */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / unmatched_transaction_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_participation_list: {
        parameters: {
            query?: {
                state?: "pending" | "confirmed" | "expired" | "requires_attention" | "cancelled" | "transferred";
                activityId?: string;
                /** @description name, e-mail, registration code or order number */
                q?: string;
                page?: number;
                limit?: number;
            };
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description items, total, page, limit */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found (unknown organizer or not a member) */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invalid_order_request (bad filter) */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_participation_detail: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                participationId: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The participation */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / participation_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_admin_order_detail: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                orderId: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The order */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / order_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_participation_cancel: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                participationId: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    reason: string;
                };
            };
        };
        responses: {
            /** @description The cancelled participation */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / participation_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description participation_transition_not_allowed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invalid_order_request (reason_required) */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_participation_restore: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                participationId: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    reason: string;
                };
            };
        };
        responses: {
            /** @description The restored participation */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / participation_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description participation_transition_not_allowed (also: the capacity is full again, blocker capacity_exhausted) */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invalid_order_request (reason_required) */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_admin_form_preview: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    items: Record<string, never>[];
                };
            };
        };
        responses: {
            /** @description formVersionId, orderFields, orderRequirements, items[] */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description unauthorized */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description forbidden */
            403: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found (an unpublished event has no form version) */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invalid_order_request */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_public_registration_form: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                orgSlug: string;
                eventSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    items: {
                        /** @example p1 */
                        itemRef?: string;
                        /** Format: uuid */
                        activityId: string;
                        /** Format: uuid */
                        variantId: string;
                        /** @description The participant is a minor: guardian requirements apply */
                        minor?: boolean;
                    }[];
                };
            };
        };
        responses: {
            /** @description formVersionId, orderFields, orderRequirements, items[] {itemRef, fields, requirements}, formToken */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invalid_order_request */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_public_order_create: {
        parameters: {
            query?: never;
            header: {
                "Idempotency-Key": string;
            };
            path: {
                orgSlug: string;
                eventSlug: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    items: Record<string, never>[];
                    persons: Record<string, never>[];
                    orderAnswers: Record<string, never>;
                    decisions?: Record<string, never>[];
                    /** @enum {string} */
                    paymentMethod?: "bank_transfer" | "cash" | "free";
                    /** @example cs */
                    locale?: string;
                    /** @description Honeypot: must stay empty */
                    website?: string;
                    /** @description From the registration form endpoint */
                    formToken?: string;
                };
            };
        };
        responses: {
            /** @description orderId, orderNumber, selfServiceUrl, payment.status (instructions_pending | cash_on_site | not_required) */
            201: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description not_found / event_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description capacity_exhausted | registration_closed | request_in_progress */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description form_invalid | missing_confirmation | not_eligible | invalid_order_request | payment_method_not_allowed | spam_suspected | idempotency_key_reused | idempotency_key_required */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description rate_limited */
            429: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_self_service_order: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description The order */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description token_invalid */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_self_service_cancel: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                participationId: string;
            };
            cookie?: never;
        };
        requestBody?: {
            content: {
                "application/json": {
                    reason?: string;
                };
            };
        };
        responses: {
            /** @description The updated order */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description token_invalid */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description participation_not_found (also a participation of another order) */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description self_service_action_not_allowed | participation_transition_not_allowed */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_self_service_correct_person: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                personId: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    answers: Record<string, never>;
                };
            };
        };
        responses: {
            /** @description The updated order */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description token_invalid */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description person_not_found */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description form_invalid | not_eligible */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    put_api_self_service_correct_contact: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    answers: Record<string, never>;
                };
            };
        };
        responses: {
            /** @description The updated order */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description token_invalid */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description form_invalid */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_self_service_confirmations: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    decisions: {
                        /** Format: uuid */
                        requirementId: string;
                        /** Format: uuid */
                        participationId?: string | null;
                        /** @enum {string} */
                        decision: "granted" | "declined" | "withdrawn";
                    }[];
                };
            };
        };
        responses: {
            /** @description The updated order */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description token_invalid */
            401: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description invalid_order_request | confirmation_not_allowed */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    post_api_public_self_service_recover: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": {
                    /** Format: email */
                    email: string;
                    orgSlug: string;
                    eventSlug?: string;
                };
            };
        };
        responses: {
            /** @description accepted */
            202: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_health_live: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Alive */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    get_api_health_ready: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Ready */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Database unavailable */
            503: {
                headers: {
                    [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
}
