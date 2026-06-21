--
-- PostgreSQL database dump
--

\restrict x8gYo59e93fKyzf8ql9df3furCIn4pgKWAzgicuhIWa23eGVlgtD2imYszJmAV5

-- Dumped from database version 18.3 (Ubuntu 18.3-1.pgdg24.04+1)
-- Dumped by pg_dump version 18.3 (Ubuntu 18.3-1.pgdg24.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: account_schedule_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.account_schedule_lines (
    id bigint NOT NULL,
    schedule_id bigint NOT NULL,
    line_no integer DEFAULT 0 NOT NULL,
    row_no character varying(20),
    description character varying(255) NOT NULL,
    totaling_type character varying(255) DEFAULT 'Posting Accounts'::character varying NOT NULL,
    totaling character varying(255),
    row_type character varying(255) DEFAULT 'Net Change'::character varying NOT NULL,
    amount_type character varying(255) DEFAULT 'Net Amount'::character varying NOT NULL,
    show_opposite_sign boolean DEFAULT false NOT NULL,
    bold boolean DEFAULT false NOT NULL,
    italic boolean DEFAULT false NOT NULL,
    underline boolean DEFAULT false NOT NULL,
    indentation integer DEFAULT 0 NOT NULL,
    new_page boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: account_schedule_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.account_schedule_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: account_schedule_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.account_schedule_lines_id_seq OWNED BY public.account_schedule_lines.id;


--
-- Name: account_schedules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.account_schedules (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: account_schedules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.account_schedules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: account_schedules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.account_schedules_id_seq OWNED BY public.account_schedules.id;


--
-- Name: accounting_periods; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.accounting_periods (
    id bigint NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    name character varying(50),
    is_closed boolean DEFAULT false NOT NULL,
    closed_at timestamp(0) without time zone,
    closed_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: accounting_periods_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.accounting_periods_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: accounting_periods_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.accounting_periods_id_seq OWNED BY public.accounting_periods.id;


--
-- Name: actual_overhead_costs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.actual_overhead_costs (
    id bigint NOT NULL,
    work_center_id bigint,
    machine_center_id bigint,
    location_id bigint,
    period date NOT NULL,
    fiscal_year integer NOT NULL,
    period_no smallint NOT NULL,
    cost_type character varying(50) NOT NULL,
    cost_type_code character varying(20),
    amount numeric(15,4) NOT NULL,
    allocated_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    remaining_amount numeric(15,4) GENERATED ALWAYS AS ((amount - allocated_amount)) NOT NULL,
    gl_account_id bigint NOT NULL,
    gl_account_no character varying(50) NOT NULL,
    document_type character varying(50),
    document_no character varying(50),
    document_date date,
    description text NOT NULL,
    notes text,
    status character varying(255) DEFAULT 'unallocated'::character varying NOT NULL,
    variance_journal_batch_id bigint,
    variance_posted_at timestamp(0) without time zone,
    created_by bigint NOT NULL,
    updated_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT actual_overhead_costs_status_check CHECK (((status)::text = ANY ((ARRAY['unallocated'::character varying, 'partial'::character varying, 'fully_allocated'::character varying, 'variance_posted'::character varying])::text[])))
);


--
-- Name: actual_overhead_costs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.actual_overhead_costs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: actual_overhead_costs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.actual_overhead_costs_id_seq OWNED BY public.actual_overhead_costs.id;


--
-- Name: allocation_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.allocation_lines (
    id bigint NOT NULL,
    allocation_id bigint NOT NULL,
    target_account_id bigint NOT NULL,
    description character varying(255),
    percentage numeric(5,2) NOT NULL,
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: allocation_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.allocation_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: allocation_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.allocation_lines_id_seq OWNED BY public.allocation_lines.id;


--
-- Name: allocations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.allocations (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    description character varying(255),
    total_percentage numeric(5,2) DEFAULT '100'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: allocations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.allocations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: allocations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.allocations_id_seq OWNED BY public.allocations.id;


--
-- Name: approval_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.approval_entries (
    id bigint NOT NULL,
    approvable_type character varying(255) NOT NULL,
    approvable_id bigint NOT NULL,
    sequence_no integer NOT NULL,
    approver_id bigint NOT NULL,
    status character varying(20) DEFAULT 'created'::character varying NOT NULL,
    approved_at timestamp(0) without time zone,
    approved_by bigint,
    rejected_at timestamp(0) without time zone,
    rejected_by bigint,
    delegated_to bigint,
    delegated_at timestamp(0) without time zone,
    comment text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: approval_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.approval_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: approval_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.approval_entries_id_seq OWNED BY public.approval_entries.id;


--
-- Name: approval_template_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.approval_template_entries (
    id bigint NOT NULL,
    approval_template_id bigint NOT NULL,
    sequence_no integer NOT NULL,
    approver_type character varying(20) NOT NULL,
    approver_id bigint,
    approver_role character varying(50),
    hierarchy_levels integer,
    dimension_code character varying(20),
    allow_delegation boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: approval_template_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.approval_template_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: approval_template_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.approval_template_entries_id_seq OWNED BY public.approval_template_entries.id;


--
-- Name: approval_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.approval_templates (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(255) NOT NULL,
    enabled boolean DEFAULT true NOT NULL,
    document_type character varying(30) NOT NULL,
    amount_limit numeric(18,4),
    vendor_posting_group_filter bigint,
    dimension_1_filter json,
    dimension_2_filter json,
    location_filter character varying(10),
    due_date_formula integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: approval_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.approval_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: approval_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.approval_templates_id_seq OWNED BY public.approval_templates.id;


--
-- Name: asset_components; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asset_components (
    id bigint NOT NULL,
    main_asset_id bigint NOT NULL,
    component_asset_id bigint NOT NULL,
    quantity numeric(20,4) DEFAULT '1'::numeric NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: asset_components_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asset_components_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asset_components_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asset_components_id_seq OWNED BY public.asset_components.id;


--
-- Name: asset_depreciation_ledger; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asset_depreciation_ledger (
    id bigint CONSTRAINT fixed_asset_depreciation_ledger_id_not_null NOT NULL,
    asset_id bigint CONSTRAINT fixed_asset_depreciation_ledger_fixed_asset_id_not_null NOT NULL,
    depreciation_date date CONSTRAINT fixed_asset_depreciation_ledger_depreciation_date_not_null NOT NULL,
    depreciation_period character varying(255) CONSTRAINT fixed_asset_depreciation_ledger_depreciation_period_not_null NOT NULL,
    depreciation_amount numeric(15,2) CONSTRAINT fixed_asset_depreciation_ledger_depreciation_amount_not_null NOT NULL,
    accumulated_depreciation numeric(15,2) CONSTRAINT fixed_asset_depreciation_ledg_accumulated_depreciation_not_null NOT NULL,
    net_book_value numeric(15,2) CONSTRAINT fixed_asset_depreciation_ledger_net_book_value_not_null NOT NULL,
    posted_document_no character varying(255),
    posted boolean DEFAULT false CONSTRAINT fixed_asset_depreciation_ledger_posted_not_null NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: asset_ledger_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asset_ledger_entries (
    id bigint NOT NULL,
    asset_id bigint NOT NULL,
    posting_date date NOT NULL,
    document_no character varying(255),
    entry_type character varying(255) NOT NULL,
    amount numeric(20,4) NOT NULL,
    description character varying(255),
    user_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    amount_lcy numeric(20,4),
    currency_id bigint
);


--
-- Name: asset_ledger_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asset_ledger_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asset_ledger_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asset_ledger_entries_id_seq OWNED BY public.asset_ledger_entries.id;


--
-- Name: asset_maintenances; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asset_maintenances (
    id bigint NOT NULL,
    asset_id bigint NOT NULL,
    maintenance_date date NOT NULL,
    vendor_id bigint,
    service_agent_id character varying(255),
    description character varying(255) NOT NULL,
    cost numeric(20,4) DEFAULT '0'::numeric NOT NULL,
    next_service_date date,
    completed boolean DEFAULT false NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: asset_maintenances_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asset_maintenances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asset_maintenances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asset_maintenances_id_seq OWNED BY public.asset_maintenances.id;


--
-- Name: assets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.assets (
    id bigint NOT NULL,
    asset_type character varying(255) NOT NULL,
    fixed_asset_category character varying(255),
    tangible_type character varying(255),
    intangible_type character varying(255),
    liquidity_type character varying(255),
    asset_no character varying(255) NOT NULL,
    description character varying(255) NOT NULL,
    description_2 character varying(255),
    search_name character varying(255),
    bank_account_id bigint,
    bank_account_no character varying(255),
    account_holder_name character varying(255),
    bank_name character varying(255),
    branch_code character varying(255),
    iban character varying(255),
    swift_code character varying(255),
    vendor_id bigint,
    customer_id bigint,
    employee_id bigint,
    reference_document_no character varying(255),
    expected_clearance_date date,
    fa_location_code character varying(255),
    serial_no character varying(255),
    registration_no character varying(255),
    main_asset_id bigint,
    acquisition_date date,
    acquisition_cost numeric(20,4) DEFAULT '0'::numeric NOT NULL,
    original_cost numeric(20,4) DEFAULT '0'::numeric NOT NULL,
    acquisition_vendor_id bigint,
    purchase_order_no character varying(255),
    purchase_invoice_no character varying(255),
    active boolean DEFAULT true NOT NULL,
    acquired boolean DEFAULT false NOT NULL,
    depreciation_method character varying(255),
    depreciation_start_date date,
    depreciation_end_date date,
    useful_life_months integer,
    salvage_value numeric(20,4) DEFAULT '0'::numeric NOT NULL,
    depreciation_rate numeric(10,4),
    book_value numeric(20,4) DEFAULT '0'::numeric NOT NULL,
    accumulated_depreciation numeric(20,4) DEFAULT '0'::numeric NOT NULL,
    last_depreciation_date date,
    opening_balance numeric(20,4) DEFAULT '0'::numeric NOT NULL,
    current_balance numeric(20,4) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(255),
    currency_factor numeric(20,6) DEFAULT '1'::numeric NOT NULL,
    last_reconciliation_date date,
    fa_posting_group_id bigint,
    asset_account_id bigint,
    accum_dep_account_id bigint,
    depreciation_expense_account_id bigint,
    gain_loss_account_id bigint,
    shortcut_dimension_1_code character varying(255),
    shortcut_dimension_2_code character varying(255),
    dimensions json,
    disposal_date date,
    disposal_proceeds numeric(20,4) DEFAULT '0'::numeric NOT NULL,
    gain_loss_on_disposal numeric(20,4) DEFAULT '0'::numeric NOT NULL,
    notes text,
    custom_attributes json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    currency_id bigint
);


--
-- Name: assets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.assets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: assets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.assets_id_seq OWNED BY public.assets.id;


--
-- Name: attendance_ledger_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.attendance_ledger_entries (
    id bigint NOT NULL,
    employee_id bigint NOT NULL,
    attendance_date date NOT NULL,
    clock_in_at timestamp(0) without time zone,
    clock_out_at timestamp(0) without time zone,
    break_minutes smallint DEFAULT '0'::smallint NOT NULL,
    worked_hours numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    status character varying(20) DEFAULT 'OPEN'::character varying NOT NULL,
    approved_by bigint,
    approved_at timestamp(0) without time zone,
    approval_note text,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: attendance_ledger_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.attendance_ledger_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: attendance_ledger_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.attendance_ledger_entries_id_seq OWNED BY public.attendance_ledger_entries.id;


--
-- Name: bank_account_ledger_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bank_account_ledger_entries (
    id bigint NOT NULL,
    entry_number integer NOT NULL,
    bank_account_id bigint NOT NULL,
    bank_account_no character varying(20),
    posting_date date NOT NULL,
    document_date date,
    due_date date,
    document_type character varying(30),
    document_no character varying(20) NOT NULL,
    external_document_no character varying(35),
    description text NOT NULL,
    description_2 text,
    entry_type character varying(30) NOT NULL,
    check_type character varying(30),
    check_no character varying(20),
    check_date date,
    amount numeric(18,4) NOT NULL,
    amount_lcy numeric(18,4) NOT NULL,
    debit_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    credit_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(10),
    currency_factor numeric(18,6) DEFAULT '1'::numeric NOT NULL,
    balance numeric(18,4) NOT NULL,
    balance_lcy numeric(18,4) NOT NULL,
    status character varying(20) DEFAULT 'open'::character varying NOT NULL,
    open boolean DEFAULT true NOT NULL,
    statement_no character varying(20),
    statement_line_no integer,
    statement_date date,
    reconciled_at timestamp(0) without time zone,
    reconciled_by bigint,
    vendor_ledger_entry_id bigint,
    customer_ledger_entry_id bigint,
    gl_entry_id bigint,
    transfer_entry_id bigint,
    source_type character varying(30),
    source_id bigint,
    source_no character varying(20),
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimensions json,
    user_id bigint NOT NULL,
    journal_batch_name character varying(20),
    journal_template_name character varying(20),
    journal_line_no integer,
    voided_at timestamp(0) without time zone,
    voided_by bigint,
    void_reason character varying(255),
    comment text,
    additional_fields json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: bank_account_ledger_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bank_account_ledger_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bank_account_ledger_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bank_account_ledger_entries_id_seq OWNED BY public.bank_account_ledger_entries.id;


--
-- Name: bank_account_statement_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bank_account_statement_lines (
    id bigint NOT NULL,
    bank_account_id bigint NOT NULL,
    statement_no character varying(20) NOT NULL,
    statement_line_no integer NOT NULL,
    transaction_date date NOT NULL,
    description character varying(255) NOT NULL,
    reference_no character varying(50),
    statement_amount numeric(18,4) NOT NULL,
    debit_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    credit_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    bank_account_ledger_entry_id bigint,
    reconciled boolean DEFAULT false NOT NULL,
    difference numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: bank_account_statement_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bank_account_statement_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bank_account_statement_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bank_account_statement_lines_id_seq OWNED BY public.bank_account_statement_lines.id;


--
-- Name: bank_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bank_accounts (
    id bigint NOT NULL,
    account_code character varying(20) NOT NULL,
    account_name character varying(255) NOT NULL,
    bank_name character varying(100) NOT NULL,
    bank_branch character varying(100),
    account_number character varying(50) NOT NULL,
    routing_number character varying(20),
    swift_code character varying(20),
    iban character varying(34),
    gl_account_id bigint NOT NULL,
    currency_id bigint,
    account_type character varying(255) DEFAULT 'CHECKING'::character varying NOT NULL,
    current_balance numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    available_balance numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    last_reconciliation_date date,
    last_reconciliation_balance numeric(15,4),
    next_check_number character varying(20),
    check_form_id character varying(20),
    active boolean DEFAULT true NOT NULL,
    allow_payments boolean DEFAULT true NOT NULL,
    allow_receipts boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT bank_accounts_account_type_check CHECK (((account_type)::text = ANY ((ARRAY['CHECKING'::character varying, 'SAVINGS'::character varying, 'MONEY_MARKET'::character varying, 'CERTIFICATE_OF_DEPOSIT'::character varying, 'FOREIGN_CURRENCY'::character varying])::text[])))
);


--
-- Name: bank_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bank_accounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bank_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bank_accounts_id_seq OWNED BY public.bank_accounts.id;


--
-- Name: bank_reconciliations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bank_reconciliations (
    id bigint NOT NULL,
    bank_account_id bigint NOT NULL,
    statement_no character varying(20) NOT NULL,
    statement_date date NOT NULL,
    statement_ending_balance numeric(18,4) NOT NULL,
    bank_balance_at_reconciliation numeric(18,4) NOT NULL,
    uncleared_deposits numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    uncleared_withdrawals numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    adjusted_bank_balance numeric(18,4) NOT NULL,
    reconciled boolean DEFAULT false NOT NULL,
    reconciled_at timestamp(0) without time zone,
    reconciled_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: bank_reconciliations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bank_reconciliations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bank_reconciliations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bank_reconciliations_id_seq OWNED BY public.bank_reconciliations.id;


--
-- Name: bin_contents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bin_contents (
    id bigint NOT NULL,
    bin_id bigint NOT NULL,
    item_id bigint NOT NULL,
    zone_id bigint,
    lot_no character varying(50),
    serial_no character varying(50),
    expiration_date date,
    quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    quantity_base numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure_code character varying(20) NOT NULL,
    picked_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    negative_adj_qty numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_cost numeric(15,4),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: bin_contents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bin_contents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bin_contents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bin_contents_id_seq OWNED BY public.bin_contents.id;


--
-- Name: bins; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bins (
    id bigint NOT NULL,
    location_id bigint NOT NULL,
    uom_id bigint,
    zone_id bigint,
    bin_code character varying(20) NOT NULL,
    bin_name character varying(100),
    barcode character varying(50),
    bin_type character varying(255) DEFAULT 'STORAGE'::character varying NOT NULL,
    warehouse_class character varying(255) DEFAULT 'standard'::character varying NOT NULL,
    maximum_weight numeric(15,4),
    maximum_volume numeric(15,4),
    maximum_items integer,
    blocked boolean DEFAULT false NOT NULL,
    block_movement_in boolean DEFAULT false NOT NULL,
    block_movement_out boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    dedicated boolean DEFAULT false NOT NULL,
    dedicated_item_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT bins_bin_type_check CHECK (((bin_type)::text = ANY ((ARRAY['RECEIVING'::character varying, 'SHIPPING'::character varying, 'PUT_AWAY'::character varying, 'PICK'::character varying, 'STORAGE'::character varying, 'QC'::character varying, 'QUALITY_CONTROL'::character varying, 'BULK'::character varying, 'PRODUCTION_SUPPLY'::character varying, 'PRODUCTION_OUTPUT'::character varying, 'COOLING'::character varying, 'FREEZING'::character varying, 'HAZARDOUS'::character varying])::text[]))),
    CONSTRAINT bins_warehouse_class_check CHECK (((warehouse_class)::text = ANY ((ARRAY['standard'::character varying, 'refrigerated'::character varying, 'frozen'::character varying, 'hazardous'::character varying, 'high_value'::character varying, 'quarantine'::character varying])::text[])))
);


--
-- Name: bins_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bins_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bins_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bins_id_seq OWNED BY public.bins.id;


--
-- Name: blanket_order_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.blanket_order_lines (
    id bigint NOT NULL,
    blanket_order_id bigint NOT NULL,
    line_number integer NOT NULL,
    type character varying(20) NOT NULL,
    no character varying(20),
    description character varying(100) NOT NULL,
    description_2 character varying(50),
    unit_of_measure character varying(10),
    quantity numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    quantity_received numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    quantity_invoiced numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    direct_unit_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    unit_cost_lcy numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    line_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    inv_discount_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    allow_invoice_disc boolean DEFAULT true NOT NULL,
    gross_weight numeric(18,4),
    net_weight numeric(18,4),
    units_per_parcel numeric(18,4),
    unit_volume numeric(18,4),
    appl_to_item_entry character varying(10),
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimension_set_id bigint,
    item_category_code character varying(20),
    product_group_code character varying(20),
    location_code character varying(10),
    bin_code character varying(20),
    expected_receipt_date date,
    planned_receipt_date date,
    requested_receipt_date date,
    promised_receipt_date date,
    purchase_order_id bigint,
    purchase_order_line_id integer,
    prod_order_no character varying(20),
    prod_order_line_no character varying(10),
    job_no character varying(20),
    job_task_no character varying(20),
    job_line_amount numeric(18,4),
    job_line_amount_lcy numeric(18,4),
    job_currency_code character varying(10),
    job_currency_factor numeric(18,6),
    whse_posting_group character varying(10),
    variant_code character varying(10),
    qty_per_unit_of_measure numeric(18,4),
    unit_of_measure_code character varying(10),
    quantity_base numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    outstanding_qty_base numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    item_charge_base_amount character varying(18),
    correction boolean DEFAULT false NOT NULL,
    cross_reference_no character varying(20),
    cross_reference_type character varying(10),
    cross_reference_type_no character varying(30),
    transaction_type character varying(10),
    transport_method character varying(10),
    attached_to_line_no character varying(10),
    entry_point character varying(10),
    area character varying(10),
    transaction_specification character varying(10),
    tax_area_code character varying(20),
    tax_liable boolean DEFAULT false NOT NULL,
    tax_group_code character varying(10),
    use_tax numeric(18,4),
    vat_bus_posting_group character varying(10),
    vat_prod_posting_group character varying(10),
    vat_base_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    system_created_entry numeric(18,4),
    vat_difference numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    inv_disc_amount_to_invoice numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    prepayment_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    prepmt_line_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    prepmt_amt_inv numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    prepmt_amt_incl_vat numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    prepayment_vat_difference numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    prepayment_vat_diff_to_deduct numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    prepayment_vat_diff_deducted numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_to_receive numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_to_invoice numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_to_assign numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_assigned numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    quantity_received_base character varying(18) DEFAULT '0'::character varying NOT NULL,
    quantity_invoiced_base character varying(18) DEFAULT '0'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    unit_price numeric(19,4),
    quantity_shipped numeric(19,4) DEFAULT '0'::numeric NOT NULL,
    sales_order_id bigint,
    sales_order_line_id bigint
);


--
-- Name: blanket_order_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.blanket_order_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: blanket_order_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.blanket_order_lines_id_seq OWNED BY public.blanket_order_lines.id;


--
-- Name: blanket_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.blanket_orders (
    id bigint NOT NULL,
    document_number character varying(20) NOT NULL,
    external_document_no character varying(35),
    vendor_id bigint,
    document_type character varying(20) DEFAULT 'BLANKET_ORDER'::character varying NOT NULL,
    status character varying(20) DEFAULT 'OPEN'::character varying NOT NULL,
    posting_date date,
    document_date date,
    order_date date,
    starting_date date,
    ending_date date,
    buyer_id bigint,
    responsibility_center character varying(10),
    assigned_user_id bigint,
    project_code character varying(20),
    department_code character varying(20),
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimension_set_id bigint,
    vendor_order_no character varying(35),
    purchase_order_no character varying(20),
    order_address_code character varying(10),
    currency_code character varying(10),
    exchange_rate numeric(19,6),
    prices_including_vat boolean DEFAULT false NOT NULL,
    payment_terms_code character varying(10),
    payment_method_code character varying(10),
    transaction_type character varying(10),
    transaction_specification character varying(10),
    transport_method character varying(10),
    entry_point character varying(10),
    area character varying(10),
    language_code character varying(10),
    format_region character varying(20),
    buy_from_vendor_name character varying(100),
    buy_from_address character varying(100),
    buy_from_address_2 character varying(50),
    buy_from_city character varying(30),
    buy_from_post_code character varying(20),
    buy_from_county character varying(30),
    buy_from_country_region_code character varying(10),
    buy_from_contact character varying(100),
    pay_to_vendor_no character varying(20),
    pay_to_name character varying(100),
    pay_to_address character varying(100),
    pay_to_address_2 character varying(50),
    pay_to_city character varying(30),
    pay_to_post_code character varying(20),
    pay_to_county character varying(30),
    pay_to_country_region_code character varying(10),
    pay_to_contact character varying(100),
    ship_to_code character varying(10),
    ship_to_name character varying(100),
    ship_to_address character varying(100),
    ship_to_address_2 character varying(50),
    ship_to_city character varying(30),
    ship_to_post_code character varying(20),
    ship_to_county character varying(30),
    ship_to_country_region_code character varying(10),
    ship_to_contact character varying(100),
    location_code character varying(10),
    shipment_method_code character varying(10),
    shipping_agent_code character varying(10),
    shipping_agent_service_code character varying(10),
    package_tracking_no character varying(30),
    invoice_disc_code character varying(20),
    requested_receipt_date date,
    promised_receipt_date date,
    quote_no character varying(20),
    comment text,
    released boolean DEFAULT false NOT NULL,
    released_at timestamp(0) without time zone,
    released_by bigint,
    created_by bigint,
    last_modified_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    order_type character varying(20) DEFAULT 'Purchase'::character varying NOT NULL,
    customer_id bigint,
    sell_to_customer_no character varying(20),
    sell_to_customer_name character varying(100),
    sell_to_address character varying(100),
    sell_to_address_2 character varying(50),
    sell_to_city character varying(30),
    sell_to_post_code character varying(20),
    sell_to_county character varying(30),
    sell_to_country_region_code character varying(10),
    sell_to_contact character varying(100),
    bill_to_customer_no character varying(20),
    bill_to_name character varying(100),
    bill_to_address character varying(100),
    bill_to_address_2 character varying(50),
    bill_to_city character varying(30),
    bill_to_post_code character varying(20),
    bill_to_county character varying(30),
    bill_to_country_region_code character varying(10),
    bill_to_contact character varying(100),
    salesperson_code character varying(20)
);


--
-- Name: blanket_orders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.blanket_orders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: blanket_orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.blanket_orders_id_seq OWNED BY public.blanket_orders.id;


--
-- Name: business_units; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.business_units (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    company_name character varying(255),
    currency_code character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: business_units_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.business_units_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: business_units_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.business_units_id_seq OWNED BY public.business_units.id;


--
-- Name: businesses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.businesses (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: businesses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.businesses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: businesses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.businesses_id_seq OWNED BY public.businesses.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: campaign_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.campaign_items (
    id bigint NOT NULL,
    campaign_id bigint NOT NULL,
    item_id bigint NOT NULL,
    special_price numeric(18,2),
    discount_percent numeric(5,2)
);


--
-- Name: campaign_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.campaign_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: campaign_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.campaign_items_id_seq OWNED BY public.campaign_items.id;


--
-- Name: campaigns; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.campaigns (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL
);


--
-- Name: campaigns_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.campaigns_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: campaigns_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.campaigns_id_seq OWNED BY public.campaigns.id;


--
-- Name: capacity_ledger_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.capacity_ledger_entries (
    id bigint NOT NULL,
    production_order_id bigint NOT NULL,
    routing_line_id bigint,
    work_center_id bigint,
    machine_center_id bigint,
    fixed_asset_id bigint,
    capex_project_id bigint,
    posting_date date NOT NULL,
    document_number character varying(255) NOT NULL,
    setup_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    run_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    stop_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    setup_time_unit character varying(255) DEFAULT 'MINUTES'::character varying NOT NULL,
    run_time_unit character varying(255) DEFAULT 'MINUTES'::character varying NOT NULL,
    output_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    scrap_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    direct_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    overhead_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    type character varying(255) DEFAULT 'RUN'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: capacity_ledger_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.capacity_ledger_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: capacity_ledger_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.capacity_ledger_entries_id_seq OWNED BY public.capacity_ledger_entries.id;


--
-- Name: capex_project_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.capex_project_lines (
    id bigint NOT NULL,
    capex_project_id bigint NOT NULL,
    line_number integer NOT NULL,
    line_type character varying(255) NOT NULL,
    description text NOT NULL,
    budget_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    committed_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    actual_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    variance_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    source_document_type character varying(255),
    source_document_id bigint,
    source_document_no character varying(255),
    source_document_date date,
    production_order_id bigint,
    production_order_component_id bigint,
    capacity_ledger_entry_id bigint,
    vendor_id bigint,
    purchase_order_number character varying(255),
    eligible_for_capitalization boolean DEFAULT true NOT NULL,
    non_capitalization_reason text,
    capitalized boolean DEFAULT false NOT NULL,
    capitalized_at timestamp(0) without time zone,
    capitalized_by bigint,
    gl_entry_reference character varying(255),
    status character varying(255) DEFAULT 'PLANNED'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: capex_project_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.capex_project_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: capex_project_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.capex_project_lines_id_seq OWNED BY public.capex_project_lines.id;


--
-- Name: capex_projects; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.capex_projects (
    id bigint NOT NULL,
    project_number character varying(255) NOT NULL,
    description character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'DRAFT'::character varying NOT NULL,
    asset_id bigint,
    budget_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    committed_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    actual_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    capitalized_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    planned_start_date date,
    planned_end_date date,
    actual_start_date date,
    actual_end_date date,
    capitalize_labor boolean DEFAULT true NOT NULL,
    capitalize_materials boolean DEFAULT true NOT NULL,
    capitalize_overhead boolean DEFAULT false NOT NULL,
    capitalize_interest boolean DEFAULT false NOT NULL,
    capitalization_threshold numeric(15,2) DEFAULT '5000'::numeric NOT NULL,
    wip_gl_account_id bigint NOT NULL,
    capex_gl_account_id bigint NOT NULL,
    interest_capitalization_rate numeric(5,2),
    capitalized_interest_to_date numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    project_manager_id bigint NOT NULL,
    approver_id bigint,
    approved_at timestamp(0) without time zone,
    created_by bigint NOT NULL,
    last_modified_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: capex_projects_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.capex_projects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: capex_projects_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.capex_projects_id_seq OWNED BY public.capex_projects.id;


--
-- Name: cash_receipt_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cash_receipt_lines (
    id bigint NOT NULL,
    journal_line_id bigint NOT NULL,
    customer_id bigint NOT NULL,
    customer_no character varying(50),
    amount_received numeric(15,4) NOT NULL,
    amount_received_lcy numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    remaining_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    bank_account_id bigint NOT NULL,
    bank_account_no character varying(50),
    applies_to_doc_type character varying(255),
    applies_to_doc_no character varying(50),
    applies_to_id bigint,
    applies_to_amount numeric(15,4),
    calculate_vat boolean DEFAULT false NOT NULL,
    payment_method_code character varying(255),
    check_no character varying(50),
    check_date date,
    exported_to_payment_jnl boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT cash_receipt_lines_applies_to_doc_type_check CHECK (((applies_to_doc_type)::text = ANY ((ARRAY['Invoice'::character varying, 'Credit Memo'::character varying, 'Payment'::character varying, 'Refund'::character varying])::text[]))),
    CONSTRAINT cash_receipt_lines_payment_method_code_check CHECK (((payment_method_code)::text = ANY ((ARRAY['Cash'::character varying, 'Check'::character varying, 'Bank Transfer'::character varying, 'Credit Card'::character varying, 'Electronic'::character varying])::text[])))
);


--
-- Name: cash_receipt_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cash_receipt_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cash_receipt_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cash_receipt_lines_id_seq OWNED BY public.cash_receipt_lines.id;


--
-- Name: categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categories (
    id bigint NOT NULL,
    category_code character varying(50) NOT NULL,
    category_name character varying(100) NOT NULL,
    hierarchy_path character varying(255) NOT NULL,
    parent_id bigint,
    level integer DEFAULT 0 NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    category_type character varying(255) DEFAULT 'THERAPEUTIC'::character varying NOT NULL,
    description text,
    attributes json,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT categories_category_type_check CHECK (((category_type)::text = ANY ((ARRAY['THERAPEUTIC'::character varying, 'BOTANICAL'::character varying, 'REGULATORY'::character varying, 'FORM'::character varying, 'SOURCE'::character varying, 'PROCESSING'::character varying])::text[])))
);


--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categories_id_seq OWNED BY public.categories.id;


--
-- Name: chart_of_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chart_of_accounts (
    id bigint NOT NULL,
    account_number character varying(50) NOT NULL,
    name character varying(100) NOT NULL,
    search_name character varying(100),
    structural_type character varying(255) DEFAULT 'posting'::character varying NOT NULL,
    account_category character varying(255) NOT NULL,
    income_balance character varying(255) DEFAULT '0'::character varying NOT NULL,
    totaling character varying(100),
    indentation smallint DEFAULT '0'::smallint NOT NULL,
    bold boolean DEFAULT false NOT NULL,
    italic boolean DEFAULT false NOT NULL,
    underline boolean DEFAULT false NOT NULL,
    show_opposite_sign boolean DEFAULT false NOT NULL,
    new_page boolean DEFAULT false NOT NULL,
    no_of_blank_lines smallint DEFAULT '0'::smallint NOT NULL,
    direct_posting boolean DEFAULT true NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    blocked_from date,
    blocked_to date,
    shortcut_dimension_1_code character varying(50),
    shortcut_dimension_2_code character varying(50),
    gen_bus_posting_group_id bigint,
    gen_prod_posting_group_id bigint,
    vat_bus_posting_group_id bigint,
    vat_prod_posting_group_id bigint,
    cost_type_no character varying(20),
    consol_debit_acc character varying(50),
    consol_credit_acc character varying(50),
    consol_translation_method character varying(255),
    parent_account_id bigint,
    balance numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    balance_at_date numeric(15,2),
    gl_account_type character varying(255) DEFAULT 'Posting'::character varying NOT NULL,
    account_type character varying(255) DEFAULT 'Asset'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: chart_of_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.chart_of_accounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: chart_of_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.chart_of_accounts_id_seq OWNED BY public.chart_of_accounts.id;


--
-- Name: company_information; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_information (
    id bigint NOT NULL,
    company_name character varying(255) NOT NULL,
    trading_name character varying(255),
    registration_no character varying(255),
    tax_registration_no character varying(255),
    tax_office character varying(255),
    address_line_1 character varying(255),
    address_line_2 character varying(255),
    city character varying(255),
    state_province character varying(255),
    postal_code character varying(255),
    country_code character varying(3) DEFAULT 'NGA'::character varying NOT NULL,
    phone_no character varying(255),
    mobile_no character varying(255),
    email character varying(255),
    website character varying(255),
    contact_person_name character varying(255),
    contact_person_title character varying(255),
    contact_person_phone character varying(255),
    contact_person_email character varying(255),
    logo_path character varying(255),
    favicon_path character varying(255),
    bank_name character varying(255),
    bank_account_no character varying(255),
    bank_branch character varying(255),
    swift_code character varying(255),
    fiscal_year_start_month character varying(255) DEFAULT '01'::character varying NOT NULL,
    base_currency_code character varying(3) DEFAULT 'NGN'::character varying NOT NULL,
    reporting_currency_code character varying(3),
    terms_conditions text,
    invoice_footer text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    business_id bigint
);


--
-- Name: company_information_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_information_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_information_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_information_id_seq OWNED BY public.company_information.id;


--
-- Name: contacts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.contacts (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    full_name character varying(255),
    company_name character varying(255),
    type character varying(20) DEFAULT 'person'::character varying NOT NULL,
    role character varying(20) DEFAULT 'customer'::character varying NOT NULL,
    email character varying(255),
    phone character varying(255),
    mobile character varying(255),
    address character varying(255),
    address_2 character varying(255),
    city character varying(255),
    state character varying(255),
    county character varying(255),
    postal_code character varying(255),
    post_code character varying(255),
    country character varying(255),
    country_region_code character varying(255),
    tax_id character varying(255),
    vat_registration_no character varying(255),
    currency character varying(255),
    currency_code character varying(255),
    payment_terms character varying(255),
    payment_terms_code character varying(255),
    general_business_posting_group_id bigint,
    vendor_posting_group_id bigint,
    vat_bus_posting_group character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: contacts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.contacts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contacts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.contacts_id_seq OWNED BY public.contacts.id;


--
-- Name: currencies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.currencies (
    id bigint NOT NULL,
    code character varying(10) NOT NULL,
    description character varying(50) NOT NULL,
    symbol character varying(5),
    decimal_places integer DEFAULT 2 NOT NULL,
    rounding_method character varying(20) DEFAULT 'nearest'::character varying NOT NULL,
    amount_rounding_precision numeric(18,4) DEFAULT 0.01 NOT NULL,
    unit_amount_rounding_precision numeric(18,4) DEFAULT 0.000010 NOT NULL,
    exchange_rate numeric(18,6) DEFAULT '1'::numeric NOT NULL,
    exchange_rate_date date,
    exchange_rate_type character varying(20) DEFAULT 'spot'::character varying NOT NULL,
    realized_gains_account_id bigint,
    realized_losses_account_id bigint,
    unrealized_gains_account_id bigint,
    unrealized_losses_account_id bigint,
    payment_tolerance_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    max_payment_tolerance_amount numeric(18,4),
    invoice_rounding boolean DEFAULT false NOT NULL,
    invoice_rounding_precision numeric(18,4),
    invoice_rounding_account_id bigint,
    receivables_account_id bigint,
    payables_account_id bigint,
    reporting_currency_code character varying(10),
    is_active boolean DEFAULT true NOT NULL,
    is_lcy boolean DEFAULT false NOT NULL,
    iso_numeric_code character varying(3),
    iso_country_code character varying(10),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: currencies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.currencies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: currencies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.currencies_id_seq OWNED BY public.currencies.id;


--
-- Name: currency_adjustment_ledger; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.currency_adjustment_ledger (
    id bigint NOT NULL,
    currency_id bigint NOT NULL,
    adjustment_account_id bigint NOT NULL,
    document_type character varying(30) NOT NULL,
    document_no character varying(20) NOT NULL,
    posting_date date NOT NULL,
    adjustment_type character varying(30) NOT NULL,
    original_amount numeric(18,4) NOT NULL,
    adjusted_amount numeric(18,4) NOT NULL,
    adjustment_amount numeric(18,4) NOT NULL,
    original_exch_rate numeric(18,6) NOT NULL,
    new_exch_rate numeric(18,6) NOT NULL,
    vendor_ledger_entry_id bigint,
    customer_ledger_entry_id bigint,
    bank_account_ledger_entry_id bigint,
    gl_entry_id bigint,
    created_by bigint NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: currency_adjustment_ledger_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.currency_adjustment_ledger_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: currency_adjustment_ledger_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.currency_adjustment_ledger_id_seq OWNED BY public.currency_adjustment_ledger.id;


--
-- Name: currency_buffers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.currency_buffers (
    id bigint NOT NULL,
    currency_id bigint NOT NULL,
    buffer_type character varying(30) NOT NULL,
    entity_id bigint NOT NULL,
    amount_lcy numeric(18,4) NOT NULL,
    amount_fcy numeric(18,4) NOT NULL,
    remaining_amount_lcy numeric(18,4) NOT NULL,
    remaining_amount_fcy numeric(18,4) NOT NULL,
    original_exch_rate numeric(18,6) NOT NULL,
    current_exch_rate numeric(18,6) NOT NULL,
    unrealized_gain_loss numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    adjusted boolean DEFAULT false NOT NULL,
    posting_date date NOT NULL,
    due_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: currency_buffers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.currency_buffers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: currency_buffers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.currency_buffers_id_seq OWNED BY public.currency_buffers.id;


--
-- Name: currency_exchange_rates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.currency_exchange_rates (
    id bigint NOT NULL,
    currency_id bigint NOT NULL,
    starting_date date NOT NULL,
    ending_date date,
    exchange_rate_amount numeric(18,6) NOT NULL,
    relational_exch_rate_amount numeric(18,6) DEFAULT '1'::numeric NOT NULL,
    adjustment_exch_rate_amount numeric(18,6),
    rate_type character varying(20) DEFAULT 'spot'::character varying NOT NULL,
    source character varying(50),
    source_reference character varying(100),
    is_current boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: currency_exchange_rates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.currency_exchange_rates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: currency_exchange_rates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.currency_exchange_rates_id_seq OWNED BY public.currency_exchange_rates.id;


--
-- Name: customer_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_groups (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: customer_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customer_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_groups_id_seq OWNED BY public.customer_groups.id;


--
-- Name: customer_ledger_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_ledger_entries (
    id bigint NOT NULL,
    entry_number bigint NOT NULL,
    customer_id bigint NOT NULL,
    document_type character varying(255) NOT NULL,
    document_number character varying(20) NOT NULL,
    external_document_number character varying(50),
    description character varying(255) NOT NULL,
    comment text,
    posting_date date NOT NULL,
    document_date date NOT NULL,
    due_date date,
    debit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    credit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    amount numeric(15,4) NOT NULL,
    running_balance numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    remaining_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    open boolean DEFAULT true NOT NULL,
    applied_to_entries json,
    fully_applied boolean DEFAULT false NOT NULL,
    currency_code character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    original_debit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    original_credit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    currency_factor numeric(15,6) DEFAULT '1'::numeric NOT NULL,
    general_business_posting_group_id bigint,
    customer_posting_group_id bigint,
    gl_entry_id bigint,
    source_id bigint,
    source_type character varying(50),
    created_by bigint NOT NULL,
    reversed boolean DEFAULT false NOT NULL,
    reversed_at timestamp(0) without time zone,
    reversed_by bigint,
    reversal_entry_number character varying(20),
    dimensions json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    currency_id bigint,
    CONSTRAINT customer_ledger_entries_document_type_check CHECK (((document_type)::text = ANY ((ARRAY['SALES_INVOICE'::character varying, 'SALES_CREDIT_MEMO'::character varying, 'PAYMENT'::character varying, 'REFUND'::character varying, 'CREDIT_MEMO_APPLICATION'::character varying, 'FINANCE_CHARGE'::character varying, 'REMINDER'::character varying, 'BANK_TRANSFER'::character varying, 'CASH_RECEIPT'::character varying, 'ADJUSTMENT'::character varying, 'WRITE_OFF'::character varying])::text[])))
);


--
-- Name: customer_ledger_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customer_ledger_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_ledger_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_ledger_entries_id_seq OWNED BY public.customer_ledger_entries.id;


--
-- Name: customer_posting_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_posting_groups (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(255) NOT NULL,
    receivables_account_id bigint NOT NULL,
    payment_disc_debit_account_id bigint,
    payment_disc_credit_account_id bigint,
    invoice_rounding_account_id bigint,
    debit_rounding_account_id bigint,
    credit_rounding_account_id bigint,
    blocked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: customer_posting_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customer_posting_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_posting_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_posting_groups_id_seq OWNED BY public.customer_posting_groups.id;


--
-- Name: customer_price_overrides; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_price_overrides (
    id bigint NOT NULL,
    customer_id bigint NOT NULL,
    item_id bigint NOT NULL,
    override_price numeric(18,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: customer_price_overrides_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customer_price_overrides_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_price_overrides_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_price_overrides_id_seq OWNED BY public.customer_price_overrides.id;


--
-- Name: customers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customers (
    id bigint NOT NULL,
    customer_number character varying(20) NOT NULL,
    name character varying(255) NOT NULL,
    address text,
    email character varying(255),
    phone character varying(255),
    general_business_posting_group_id bigint NOT NULL,
    customer_posting_group_id bigint NOT NULL,
    vat_bus_posting_group character varying(20),
    customer_group_id bigint,
    location_id bigint,
    shipping_agent_code character varying(20),
    payment_terms_code character varying(20),
    credit_limit numeric(15,2),
    blocked boolean DEFAULT false NOT NULL,
    blocked_reason character varying(255) DEFAULT 'NONE'::character varying NOT NULL,
    contact_id bigint NOT NULL,
    pricing_group_id bigint,
    vat_business_posting_group_id bigint,
    price_list_code character varying(20),
    allow_discounts boolean DEFAULT true NOT NULL,
    maximum_discount_percent numeric(5,2),
    price_includes_vat boolean DEFAULT false NOT NULL,
    customer_type character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_price_inclusive boolean DEFAULT false NOT NULL,
    CONSTRAINT customers_blocked_reason_check CHECK (((blocked_reason)::text = ANY ((ARRAY['NONE'::character varying, 'PAYMENT'::character varying, 'INVOICE'::character varying, 'INACTIVE'::character varying, 'ALL'::character varying])::text[])))
);


--
-- Name: customers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customers_id_seq OWNED BY public.customers.id;


--
-- Name: default_dimensions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.default_dimensions (
    id bigint NOT NULL,
    table_id character varying(50) NOT NULL,
    no character varying(50) NOT NULL,
    dimension_code character varying(20) NOT NULL,
    dimension_value_code character varying(20),
    value_posting character varying(255) DEFAULT 'same_code'::character varying NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT default_dimensions_value_posting_check CHECK (((value_posting)::text = ANY ((ARRAY['none'::character varying, 'code_mandatory'::character varying, 'same_code'::character varying, 'no_code'::character varying, 'Manual'::character varying])::text[])))
);


--
-- Name: default_dimensions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.default_dimensions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: default_dimensions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.default_dimensions_id_seq OWNED BY public.default_dimensions.id;


--
-- Name: department_employee; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.department_employee (
    id bigint NOT NULL,
    department_id bigint NOT NULL,
    employee_id bigint NOT NULL,
    assignment_type character varying(20) DEFAULT 'primary'::character varying NOT NULL,
    position_title character varying(100),
    assignment_date date NOT NULL,
    end_date date,
    allocation_percentage numeric(5,2) DEFAULT '100'::numeric NOT NULL,
    is_default_dimension boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: department_employee_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.department_employee_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: department_employee_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.department_employee_id_seq OWNED BY public.department_employee.id;


--
-- Name: departments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.departments (
    id bigint NOT NULL,
    department_code character varying(20) NOT NULL,
    name character varying(100) NOT NULL,
    search_name character varying(100),
    parent_department_id bigint,
    level integer DEFAULT 0 NOT NULL,
    department_path character varying(255),
    type character varying(30) DEFAULT 'operating'::character varying NOT NULL,
    status character varying(20) DEFAULT 'active'::character varying NOT NULL,
    dimension_value_id bigint,
    global_dimension_1_code character varying(20),
    is_cost_center boolean DEFAULT true NOT NULL,
    is_profit_center boolean DEFAULT false NOT NULL,
    cost_center_code character varying(20),
    profit_center_code character varying(20),
    manager_id bigint,
    approver_id bigint,
    location_code character varying(10),
    annual_budget numeric(18,4),
    budget_utilized numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    default_expense_account character varying(20),
    default_project_code character varying(20),
    email character varying(100),
    phone character varying(30),
    room_location character varying(50),
    starting_date date,
    ending_date date,
    notes text,
    blocked_at timestamp(0) without time zone,
    blocked_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: departments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.departments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: departments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.departments_id_seq OWNED BY public.departments.id;


--
-- Name: depreciation_books; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.depreciation_books (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(100) NOT NULL,
    book_type character varying(255) DEFAULT 'corporate'::character varying NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    default_depreciation_method character varying(255) DEFAULT 'straight_line'::character varying NOT NULL,
    default_calculation_method character varying(255) DEFAULT 'straight_line'::character varying NOT NULL,
    integrate_with_gl boolean DEFAULT true NOT NULL,
    use_rounding boolean DEFAULT true NOT NULL,
    rounding_precision integer DEFAULT 2 NOT NULL,
    align_fiscal_year boolean DEFAULT true NOT NULL,
    fiscal_year_start integer,
    is_active boolean DEFAULT true NOT NULL,
    acquisition_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    accumulated_depreciation numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT depreciation_books_book_type_check CHECK (((book_type)::text = ANY ((ARRAY['corporate'::character varying, 'tax'::character varying, 'accounting'::character varying, 'gaap'::character varying, 'ifrs'::character varying, 'custom'::character varying])::text[]))),
    CONSTRAINT depreciation_books_default_calculation_method_check CHECK (((default_calculation_method)::text = ANY ((ARRAY['straight_line'::character varying, 'db1_sl'::character varying, 'db2_sl'::character varying])::text[]))),
    CONSTRAINT depreciation_books_default_depreciation_method_check CHECK (((default_depreciation_method)::text = ANY ((ARRAY['straight_line'::character varying, 'declining_balance'::character varying, 'double_declining'::character varying, 'reducing_balance'::character varying, 'units_of_production'::character varying, 'sum_of_years_digits'::character varying, 'manual'::character varying, 'none'::character varying])::text[])))
);


--
-- Name: depreciation_books_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.depreciation_books_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: depreciation_books_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.depreciation_books_id_seq OWNED BY public.depreciation_books.id;


--
-- Name: dimension_combinations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dimension_combinations (
    id bigint NOT NULL,
    dimension_1_code character varying(20) NOT NULL,
    dimension_2_code character varying(20) NOT NULL,
    combination_type character varying(255) DEFAULT 'no_limitation'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT dimension_combinations_combination_type_check CHECK (((combination_type)::text = ANY ((ARRAY['no_limitation'::character varying, 'limited'::character varying, 'blocked'::character varying])::text[])))
);


--
-- Name: dimension_combinations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dimension_combinations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dimension_combinations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dimension_combinations_id_seq OWNED BY public.dimension_combinations.id;


--
-- Name: dimension_set_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dimension_set_entries (
    id bigint NOT NULL,
    dimension_set_id bigint NOT NULL,
    dimension_code character varying(20) NOT NULL,
    dimension_value_code character varying(20) NOT NULL,
    dimension_name character varying(100),
    dimension_value_name character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dimension_set_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dimension_set_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dimension_set_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dimension_set_entries_id_seq OWNED BY public.dimension_set_entries.id;


--
-- Name: dimension_set_tree_nodes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dimension_set_tree_nodes (
    id bigint NOT NULL,
    parent_dimension_set_id bigint DEFAULT '0'::bigint NOT NULL,
    dimension_value_id bigint NOT NULL,
    dimension_set_id bigint NOT NULL,
    in_use boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dimension_set_tree_nodes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dimension_set_tree_nodes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dimension_set_tree_nodes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dimension_set_tree_nodes_id_seq OWNED BY public.dimension_set_tree_nodes.id;


--
-- Name: dimension_sets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dimension_sets (
    id bigint NOT NULL,
    description character varying(250),
    dimension_hash character varying(32),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dimension_sets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dimension_sets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dimension_sets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dimension_sets_id_seq OWNED BY public.dimension_sets.id;


--
-- Name: dimension_value_combinations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dimension_value_combinations (
    id bigint NOT NULL,
    dimension_combination_id bigint NOT NULL,
    dimension_1_value_code character varying(20) NOT NULL,
    dimension_2_value_code character varying(20) NOT NULL,
    blocked boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dimension_value_combinations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dimension_value_combinations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dimension_value_combinations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dimension_value_combinations_id_seq OWNED BY public.dimension_value_combinations.id;


--
-- Name: dimension_values; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dimension_values (
    id bigint NOT NULL,
    dimension_id bigint NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(100) NOT NULL,
    dimension_value_type character varying(255) DEFAULT 'standard'::character varying NOT NULL,
    parent_id bigint,
    indentation integer DEFAULT 0 NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    starting_date date,
    ending_date date,
    global_dimension_no character varying(10),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT dimension_values_dimension_value_type_check CHECK (((dimension_value_type)::text = ANY ((ARRAY['standard'::character varying, 'begin_total'::character varying, 'end_total'::character varying])::text[])))
);


--
-- Name: dimension_values_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dimension_values_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dimension_values_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dimension_values_id_seq OWNED BY public.dimension_values.id;


--
-- Name: dimensions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dimensions (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(100) NOT NULL,
    code_caption character varying(100),
    filter_caption character varying(100),
    description character varying(250),
    blocked boolean DEFAULT false NOT NULL,
    dimension_type character varying(255) DEFAULT 'regular'::character varying NOT NULL,
    global_dimension_no smallint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT dimensions_dimension_type_check CHECK (((dimension_type)::text = ANY ((ARRAY['global'::character varying, 'shortcut'::character varying, 'regular'::character varying])::text[])))
);


--
-- Name: dimensions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dimensions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dimensions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dimensions_id_seq OWNED BY public.dimensions.id;


--
-- Name: discount_rules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.discount_rules (
    id bigint NOT NULL,
    item_id bigint,
    customer_group_id bigint,
    discount_percent numeric(5,2) NOT NULL,
    start_date date NOT NULL,
    end_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: discount_rules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.discount_rules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: discount_rules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.discount_rules_id_seq OWNED BY public.discount_rules.id;


--
-- Name: document_headers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_headers (
    id bigint NOT NULL,
    doc_type character varying(255) DEFAULT 'PURCHASE_ORDER'::character varying NOT NULL,
    doc_no character varying(50) NOT NULL,
    doc_date date NOT NULL,
    posting_date date NOT NULL,
    status character varying(20) DEFAULT 'OPEN'::character varying NOT NULL,
    created_by bigint NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT document_headers_doc_type_check CHECK (((doc_type)::text = ANY ((ARRAY['PAYMENT'::character varying, 'INVOICE'::character varying, 'RECEIPT'::character varying, 'CREDIT_NOTE'::character varying, 'DEBIT_NOTE'::character varying, 'REFUNDED_PAYMENT'::character varying, 'REFUNDED_INVOICE'::character varying, 'FINANCE_CHARGE'::character varying, 'CREDIT_MEMO'::character varying, 'DEBIT_MEMO'::character varying, 'CASH_RECEIPT'::character varying, 'QUOTE'::character varying, 'ORDER'::character varying, 'BILL_OF_LADING'::character varying, 'CERTIFICATE_OF_ORIGIN'::character varying, 'CERTIFICATE_OF_DELIVERY'::character varying, 'CERTIFICATE_OF_ORIGIN_AND_DELIVERY'::character varying, 'PURCHASE_ORDER'::character varying, 'PRODUCTION_ORDER'::character varying, 'SALES_ORDER'::character varying, 'TRANSFER_ORDER'::character varying, 'ADJUSTMENT'::character varying, 'RETURN'::character varying, 'SCRAP'::character varying])::text[])))
);


--
-- Name: document_headers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.document_headers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: document_headers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.document_headers_id_seq OWNED BY public.document_headers.id;


--
-- Name: employee_bank_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employee_bank_accounts (
    id bigint NOT NULL,
    employee_id bigint NOT NULL,
    bank_code character varying(20) NOT NULL,
    bank_name character varying(255) NOT NULL,
    account_number character varying(255) NOT NULL,
    account_name character varying(255) NOT NULL,
    is_primary boolean DEFAULT false NOT NULL,
    payment_method character varying(255) DEFAULT 'Bank Transfer'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT employee_bank_accounts_payment_method_check CHECK (((payment_method)::text = ANY ((ARRAY['Bank Transfer'::character varying, 'Check'::character varying, 'Cash'::character varying])::text[])))
);


--
-- Name: employee_bank_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employee_bank_accounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_bank_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_bank_accounts_id_seq OWNED BY public.employee_bank_accounts.id;


--
-- Name: employee_compensation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employee_compensation (
    id bigint NOT NULL,
    employee_id bigint NOT NULL,
    effective_date date NOT NULL,
    base_salary numeric(15,4) NOT NULL,
    reason_code character varying(255),
    job_title character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    audit_note text
);


--
-- Name: employee_compensation_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employee_compensation_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_compensation_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_compensation_id_seq OWNED BY public.employee_compensation.id;


--
-- Name: employee_pay_codes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employee_pay_codes (
    id bigint NOT NULL,
    employee_id bigint NOT NULL,
    pay_code_id bigint NOT NULL,
    amount numeric(12,2),
    percentage numeric(5,2),
    effective_date date NOT NULL,
    end_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: employee_pay_codes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employee_pay_codes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_pay_codes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_pay_codes_id_seq OWNED BY public.employee_pay_codes.id;


--
-- Name: employee_posting_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employee_posting_groups (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(255),
    payables_account_id bigint,
    blocked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: employee_posting_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employee_posting_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_posting_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_posting_groups_id_seq OWNED BY public.employee_posting_groups.id;


--
-- Name: employee_promotion_histories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employee_promotion_histories (
    id bigint NOT NULL,
    employee_id bigint NOT NULL,
    effective_date date NOT NULL,
    reason_code character varying(255) DEFAULT 'PROMOTION'::character varying NOT NULL,
    old_job_title character varying(255),
    new_job_title character varying(255) NOT NULL,
    old_department_id bigint,
    new_department_id bigint,
    old_base_salary numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    new_base_salary numeric(15,4) NOT NULL,
    audit_note text NOT NULL,
    promoted_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: employee_promotion_histories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employee_promotion_histories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_promotion_histories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_promotion_histories_id_seq OWNED BY public.employee_promotion_histories.id;


--
-- Name: employee_ytd_balances; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employee_ytd_balances (
    id bigint NOT NULL,
    employee_id bigint NOT NULL,
    year integer NOT NULL,
    gross_earnings numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    tax_deducted numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    social_security_employee numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    social_security_employer numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    net_paid numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: employee_ytd_balances_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employee_ytd_balances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_ytd_balances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_ytd_balances_id_seq OWNED BY public.employee_ytd_balances.id;


--
-- Name: employees; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employees (
    id bigint NOT NULL,
    employee_number character varying(20) NOT NULL,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    full_name character varying(255) GENERATED ALWAYS AS ((((first_name)::text || ' '::text) || (last_name)::text)) NOT NULL,
    email character varying(255),
    phone character varying(255),
    job_title character varying(255),
    business_code character varying(255),
    factory_code character varying(255),
    department_code character varying(255),
    employee_posting_group_id bigint,
    payroll_posting_group_id bigint,
    assignment_type character varying(255) DEFAULT 'corporate'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    department_id bigint
);


--
-- Name: employees_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employees_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employees_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employees_id_seq OWNED BY public.employees.id;


--
-- Name: expense_allocations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.expense_allocations (
    id bigint NOT NULL,
    expense_transaction_id bigint NOT NULL,
    allocation_basis character varying(30) NOT NULL,
    allocation_percentage numeric(5,2) NOT NULL,
    allocated_amount numeric(18,4) NOT NULL,
    target_dimension_1 character varying(20),
    target_dimension_2 character varying(20),
    target_gl_account_id bigint NOT NULL,
    gl_entry_id bigint,
    allocation_type character varying(255) DEFAULT 'percentage'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    title character varying(120)
);


--
-- Name: COLUMN expense_allocations.allocation_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.expense_allocations.allocation_type IS 'percentage, amount';


--
-- Name: expense_allocations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.expense_allocations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: expense_allocations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.expense_allocations_id_seq OWNED BY public.expense_allocations.id;


--
-- Name: expense_budgets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.expense_budgets (
    id bigint NOT NULL,
    budget_name character varying(50) NOT NULL,
    fiscal_year integer NOT NULL,
    account_type character varying(30) NOT NULL,
    category_code character varying(30) NOT NULL,
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    january numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    february numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    march numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    april numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    may numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    june numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    july numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    august numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    september numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    october numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    november numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    december numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    annual_total numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    currency_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    dimension_set_id bigint
);


--
-- Name: expense_budgets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.expense_budgets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: expense_budgets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.expense_budgets_id_seq OWNED BY public.expense_budgets.id;


--
-- Name: expense_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.expense_categories (
    id bigint NOT NULL,
    account_type character varying(30) NOT NULL,
    category_code character varying(30) NOT NULL,
    category_type character varying(30) NOT NULL,
    description character varying(100) NOT NULL,
    notes text,
    is_direct boolean DEFAULT false NOT NULL,
    is_variable boolean DEFAULT true NOT NULL,
    is_controllable boolean DEFAULT true NOT NULL,
    category_id bigint,
    expense_account_id bigint,
    contra_account_id bigint,
    posting_rules json,
    default_dimension_1 character varying(20),
    default_dimension_2 character varying(20),
    gen_prod_posting_group_id bigint,
    vat_prod_posting_group_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: expense_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.expense_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: expense_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.expense_categories_id_seq OWNED BY public.expense_categories.id;


--
-- Name: expense_transactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.expense_transactions (
    id bigint NOT NULL,
    document_type character varying(30) NOT NULL,
    document_no character varying(20) NOT NULL,
    posting_date date NOT NULL,
    document_date date,
    account_type character varying(30) NOT NULL,
    category_code character varying(30) NOT NULL,
    expense_type character varying(30),
    amount numeric(18,4) NOT NULL,
    amount_lcy numeric(18,4) NOT NULL,
    currency_code character varying(10),
    currency_factor numeric(18,6) DEFAULT '1'::numeric NOT NULL,
    vat_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    vat_bus_posting_group character varying(20),
    vendor_id bigint,
    customer_id bigint,
    employee_id bigint,
    item_id bigint,
    category_id bigint,
    currency_id bigint,
    purchase_order_no character varying(20),
    sales_order_no character varying(20),
    invoice_no character varying(20),
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimensions json,
    gl_entry_id bigint,
    expense_account_id bigint NOT NULL,
    status character varying(20) DEFAULT 'posted'::character varying NOT NULL,
    reversed_by bigint,
    posted_by bigint NOT NULL,
    description text,
    gen_bus_posting_group_id bigint,
    gen_prod_posting_group_id bigint,
    vat_bus_posting_group_id bigint,
    vat_prod_posting_group_id bigint,
    dimension_set_id bigint,
    source_type character varying(30),
    source_no character varying(50),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: COLUMN expense_transactions.source_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.expense_transactions.source_type IS 'VENDOR, CUSTOMER, EMPLOYEE, BANK, FA';


--
-- Name: expense_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.expense_transactions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: expense_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.expense_transactions_id_seq OWNED BY public.expense_transactions.id;


--
-- Name: fa_classes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fa_classes (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(100) NOT NULL,
    fa_type character varying(255) NOT NULL,
    default_posting_group_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT fa_classes_fa_type_check CHECK (((fa_type)::text = ANY ((ARRAY['tangible'::character varying, 'intangible'::character varying, 'financial'::character varying, 'operating'::character varying, 'right_of_use'::character varying])::text[])))
);


--
-- Name: fa_classes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fa_classes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fa_classes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fa_classes_id_seq OWNED BY public.fa_classes.id;


--
-- Name: fa_insurance_policies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fa_insurance_policies (
    id bigint NOT NULL,
    fixed_asset_id bigint NOT NULL,
    policy_no character varying(50) NOT NULL,
    insurance_vendor_id bigint NOT NULL,
    coverage_amount numeric(15,4) NOT NULL,
    premium_amount numeric(15,4) NOT NULL,
    start_date date NOT NULL,
    expiry_date date NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT fa_insurance_policies_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'expired'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: fa_insurance_policies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fa_insurance_policies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fa_insurance_policies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fa_insurance_policies_id_seq OWNED BY public.fa_insurance_policies.id;


--
-- Name: fa_journal_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fa_journal_batches (
    id bigint NOT NULL,
    template_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    assigned_user_id bigint,
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    depreciation_book_id bigint,
    posting_date date,
    calculate_depreciation boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT fa_journal_batches_status_check CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'released'::character varying, 'posted'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: fa_journal_batches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fa_journal_batches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fa_journal_batches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fa_journal_batches_id_seq OWNED BY public.fa_journal_batches.id;


--
-- Name: fa_journal_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fa_journal_lines (
    id bigint NOT NULL,
    batch_id bigint NOT NULL,
    line_no integer NOT NULL,
    fixed_asset_id bigint NOT NULL,
    fa_no character varying(20),
    posting_date date NOT NULL,
    fa_posting_type character varying(255) NOT NULL,
    document_no character varying(50) NOT NULL,
    description character varying(255),
    amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    calculated_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    number_of_duplication integer DEFAULT 0 NOT NULL,
    number_of_depreciation_days numeric(15,4),
    calculate_depreciation boolean DEFAULT false NOT NULL,
    index_factor numeric(15,6) DEFAULT '0'::numeric NOT NULL,
    revaluation_amount numeric(15,4),
    disposal_proceeds numeric(15,4),
    disposal_date date,
    fa_posting_group_id bigint,
    override_account_id bigint,
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimension_set_entry json,
    line_status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    fa_ledger_entry_id bigint,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: fa_journal_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fa_journal_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fa_journal_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fa_journal_lines_id_seq OWNED BY public.fa_journal_lines.id;


--
-- Name: fa_journal_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fa_journal_templates (
    id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    template_type character varying(255) DEFAULT 'acquisition'::character varying NOT NULL,
    number_series_id bigint NOT NULL,
    posting_number_series_id bigint,
    source_code character varying(20),
    default_depreciation_book_id bigint NOT NULL,
    test_report_before_posting boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT fa_journal_templates_template_type_check CHECK (((template_type)::text = ANY ((ARRAY['acquisition'::character varying, 'depreciation'::character varying, 'revaluation'::character varying, 'disposal'::character varying, 'maintenance'::character varying])::text[])))
);


--
-- Name: fa_journal_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fa_journal_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fa_journal_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fa_journal_templates_id_seq OWNED BY public.fa_journal_templates.id;


--
-- Name: fa_ledger_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fa_ledger_entries (
    id bigint NOT NULL,
    fixed_asset_id bigint NOT NULL,
    depreciation_book_id bigint NOT NULL,
    entry_no integer NOT NULL,
    fa_posting_type character varying(255) NOT NULL,
    document_type character varying(50),
    document_no character varying(50),
    document_line_no integer,
    posting_date date NOT NULL,
    gl_entry_id bigint,
    amount numeric(15,4) NOT NULL,
    amount_lcy numeric(15,4) NOT NULL,
    depreciation_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    accumulated_depreciation numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    book_value_after numeric(15,4) NOT NULL,
    number_of_depreciation_days numeric(8,2),
    depreciation_period integer,
    revaluation_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    index_factor numeric(10,6),
    proceeds_on_disposal numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    gain_loss_on_disposal numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    description text NOT NULL,
    comment text,
    source_code character varying(20),
    journal_batch_id bigint,
    journal_batch_type character varying(50),
    created_by bigint NOT NULL,
    entry_timestamp timestamp(0) without time zone NOT NULL,
    reversed_entry_fixed_asset_id bigint,
    reversed_entry_depreciation_book_id bigint,
    reversed_entry_no integer,
    reversed boolean DEFAULT false NOT NULL,
    reversed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT fa_ledger_entries_fa_posting_type_check CHECK (((fa_posting_type)::text = ANY ((ARRAY['acquisition'::character varying, 'depreciation'::character varying, 'appreciation'::character varying, 'write_down'::character varying, 'disposal'::character varying, 'disposal_gain'::character varying, 'disposal_loss'::character varying, 'maintenance'::character varying, 'upgrade'::character varying, 'transfer'::character varying, 'split'::character varying, 'combine'::character varying, 'revaluation'::character varying, 'depreciation_accelerated'::character varying])::text[])))
);


--
-- Name: fa_ledger_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fa_ledger_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fa_ledger_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fa_ledger_entries_id_seq OWNED BY public.fa_ledger_entries.id;


--
-- Name: fa_locations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fa_locations (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(100) NOT NULL,
    location_id bigint,
    responsible_employee_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: fa_locations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fa_locations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fa_locations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fa_locations_id_seq OWNED BY public.fa_locations.id;


--
-- Name: fa_maintenance_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fa_maintenance_logs (
    id bigint NOT NULL,
    fixed_asset_id bigint NOT NULL,
    service_date date NOT NULL,
    service_type character varying(255) NOT NULL,
    description text NOT NULL,
    cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    capitalized boolean DEFAULT false NOT NULL,
    vendor_id bigint,
    maintenance_contract_id bigint,
    next_service_date date,
    created_by bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT fa_maintenance_logs_service_type_check CHECK (((service_type)::text = ANY ((ARRAY['preventive'::character varying, 'corrective'::character varying, 'upgrade'::character varying, 'inspection'::character varying])::text[])))
);


--
-- Name: fa_maintenance_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fa_maintenance_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fa_maintenance_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fa_maintenance_logs_id_seq OWNED BY public.fa_maintenance_logs.id;


--
-- Name: fa_posting_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fa_posting_groups (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(100) NOT NULL,
    acquisition_cost_account_id bigint NOT NULL,
    acquisition_cost_account_id_lcy bigint,
    depreciation_expense_account_id bigint NOT NULL,
    accumulated_depreciation_account_id bigint,
    revaluation_account_id bigint,
    reversal_of_revaluation_id bigint,
    disposal_proceeds_account_id bigint NOT NULL,
    disposal_gain_account_id bigint,
    disposal_loss_account_id bigint,
    maintenance_expense_account_id bigint,
    capitalization_account_id bigint,
    tax_depreciation_account_id bigint,
    deferred_tax_account_id bigint,
    auto_depreciate_acquisition_year boolean DEFAULT true NOT NULL,
    depreciation_calculation character varying(255) DEFAULT 'pro_rata'::character varying NOT NULL,
    depreciation_start character varying(255) DEFAULT 'acquisition'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    appreciation_account_id bigint,
    revaluation_gain_account_id bigint,
    CONSTRAINT fa_posting_groups_depreciation_calculation_check CHECK (((depreciation_calculation)::text = ANY ((ARRAY['full_year'::character varying, 'pro_rata'::character varying, 'half_year'::character varying])::text[]))),
    CONSTRAINT fa_posting_groups_depreciation_start_check CHECK (((depreciation_start)::text = ANY ((ARRAY['acquisition'::character varying, 'first_day_next_month'::character varying])::text[])))
);


--
-- Name: fa_posting_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fa_posting_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fa_posting_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fa_posting_groups_id_seq OWNED BY public.fa_posting_groups.id;


--
-- Name: fa_subclasses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fa_subclasses (
    id bigint NOT NULL,
    fa_class_id bigint NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(100) NOT NULL,
    default_posting_group_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: fa_subclasses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fa_subclasses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fa_subclasses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fa_subclasses_id_seq OWNED BY public.fa_subclasses.id;


--
-- Name: factories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.factories (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(255) NOT NULL,
    business_id bigint NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: factories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.factories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: factories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.factories_id_seq OWNED BY public.factories.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: fiscal_reopen_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fiscal_reopen_logs (
    id bigint NOT NULL,
    previous_allow_posting_from date,
    previous_allow_posting_to date,
    new_allow_posting_from date NOT NULL,
    new_allow_posting_to date NOT NULL,
    reason character varying(255) NOT NULL,
    requested_by bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: fiscal_reopen_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fiscal_reopen_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fiscal_reopen_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fiscal_reopen_logs_id_seq OWNED BY public.fiscal_reopen_logs.id;


--
-- Name: fixed_asset_depreciation_ledger_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fixed_asset_depreciation_ledger_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fixed_asset_depreciation_ledger_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fixed_asset_depreciation_ledger_id_seq OWNED BY public.asset_depreciation_ledger.id;


--
-- Name: fixed_asset_journal_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fixed_asset_journal_batches (
    id bigint NOT NULL,
    template_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255),
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: fixed_asset_journal_batches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fixed_asset_journal_batches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fixed_asset_journal_batches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fixed_asset_journal_batches_id_seq OWNED BY public.fixed_asset_journal_batches.id;


--
-- Name: fixed_asset_journal_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fixed_asset_journal_lines (
    id bigint NOT NULL,
    batch_id bigint NOT NULL,
    line_no integer DEFAULT 10000 NOT NULL,
    posting_date date NOT NULL,
    document_no character varying(255) NOT NULL,
    asset_id bigint NOT NULL,
    fa_posting_type character varying(255) NOT NULL,
    amount numeric(20,4) NOT NULL,
    description character varying(255),
    created_by bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: fixed_asset_journal_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fixed_asset_journal_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fixed_asset_journal_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fixed_asset_journal_lines_id_seq OWNED BY public.fixed_asset_journal_lines.id;


--
-- Name: fixed_asset_journal_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fixed_asset_journal_templates (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255),
    number_series_id bigint,
    source_code character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: fixed_asset_journal_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fixed_asset_journal_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fixed_asset_journal_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fixed_asset_journal_templates_id_seq OWNED BY public.fixed_asset_journal_templates.id;


--
-- Name: fixed_assets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fixed_assets (
    id bigint NOT NULL,
    fa_no character varying(50) NOT NULL,
    description character varying(100) NOT NULL,
    description_2 character varying(100),
    search_description character varying(100),
    fa_type character varying(255) DEFAULT 'fixed_asset'::character varying NOT NULL,
    fa_class_id bigint,
    fa_subclass_id bigint,
    fa_location_id bigint,
    fa_posting_group_id bigint NOT NULL,
    depreciation_book_id bigint NOT NULL,
    serial_no character varying(100),
    barcode character varying(100),
    responsible_employee_id bigint,
    vendor_id bigint,
    main_vendor_id bigint,
    location_id bigint,
    fa_location_code character varying(50),
    acquisition_date date,
    depreciation_starting_date date,
    depreciation_ending_date date,
    acquisition_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    acquisition_vendor_id bigint,
    acquisition_invoice_no character varying(50),
    depreciation_method character varying(255) DEFAULT 'straight_line'::character varying NOT NULL,
    depreciation_rate numeric(7,4),
    useful_life_years integer,
    useful_life_months integer,
    salvage_value numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    salvage_value_percentage numeric(5,2),
    total_estimated_units numeric(15,4),
    units_produced_to_date numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    declining_balance_calc character varying(255),
    book_value numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    accumulated_depreciation numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    net_book_value numeric(15,4) GENERATED ALWAYS AS ((book_value - accumulated_depreciation)) NOT NULL,
    last_revaluation_amount numeric(15,4),
    last_revaluation_date date,
    revaluation_reserve numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    insurance_value numeric(15,4),
    insurance_expiry_date date,
    insurance_policy_no character varying(50),
    status character varying(255) DEFAULT 'new'::character varying NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    blocked_reason text,
    disposal_date date,
    disposal_proceeds numeric(15,4),
    disposal_cost numeric(15,4),
    disposal_gain_loss numeric(15,4),
    shortcut_dimension_1_code character varying(50),
    shortcut_dimension_2_code character varying(50),
    dimension_set_entry json,
    created_by bigint NOT NULL,
    modified_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT fixed_assets_declining_balance_calc_check CHECK (((declining_balance_calc)::text = ANY ((ARRAY['straight_line'::character varying, 'db1_sl'::character varying, 'db2_sl'::character varying])::text[]))),
    CONSTRAINT fixed_assets_depreciation_method_check CHECK (((depreciation_method)::text = ANY ((ARRAY['straight_line'::character varying, 'declining_balance'::character varying, 'double_declining'::character varying, 'reducing_balance'::character varying, 'units_of_production'::character varying, 'sum_of_years_digits'::character varying, 'manual'::character varying, 'none'::character varying])::text[]))),
    CONSTRAINT fixed_assets_fa_type_check CHECK (((fa_type)::text = ANY ((ARRAY['tangible'::character varying, 'intangible'::character varying, 'financial'::character varying, 'operating'::character varying, 'right_of_use'::character varying])::text[]))),
    CONSTRAINT fixed_assets_status_check CHECK (((status)::text = ANY ((ARRAY['new'::character varying, 'active'::character varying, 'under_construction'::character varying, 'dismantled'::character varying, 'disposed'::character varying, 'sold'::character varying, 'transferred'::character varying])::text[])))
);


--
-- Name: fixed_assets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fixed_assets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fixed_assets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fixed_assets_id_seq OWNED BY public.fixed_assets.id;


--
-- Name: general_business_posting_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.general_business_posting_groups (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(255) NOT NULL,
    default_vat_business_posting_group_id bigint,
    auto_create_vat_bus_posting_group boolean DEFAULT false CONSTRAINT general_business_posting_gr_auto_create_vat_bus_postin_not_null NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN general_business_posting_groups.auto_create_vat_bus_posting_group; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.general_business_posting_groups.auto_create_vat_bus_posting_group IS 'Automatically assign default VAT business group';


--
-- Name: general_business_posting_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.general_business_posting_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: general_business_posting_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.general_business_posting_groups_id_seq OWNED BY public.general_business_posting_groups.id;


--
-- Name: general_journal_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.general_journal_batches (
    id bigint NOT NULL,
    template_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    assigned_user_id bigint,
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    dimension_filter json,
    balancing_account_id bigint,
    reason_code character varying(20),
    copy_dimensions_from_line boolean DEFAULT false NOT NULL,
    posting_date_restriction_from timestamp(0) without time zone,
    posting_date_restriction_to timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT general_journal_batches_status_check CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'released'::character varying, 'posted'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: general_journal_batches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.general_journal_batches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: general_journal_batches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.general_journal_batches_id_seq OWNED BY public.general_journal_batches.id;


--
-- Name: general_journal_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.general_journal_lines (
    id bigint NOT NULL,
    batch_id bigint NOT NULL,
    line_no integer DEFAULT 10000 NOT NULL,
    posting_date date NOT NULL,
    document_type character varying(30),
    document_no character varying(50),
    external_document_no character varying(50),
    account_id bigint NOT NULL,
    account_type character varying(20) DEFAULT 'gl'::character varying NOT NULL,
    balancing_account_id bigint,
    description text NOT NULL,
    debit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    credit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    amount_lcy numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(10),
    currency_factor numeric(15,6),
    amount_currency numeric(15,4),
    shortcut_dimension_1_code character varying(50),
    shortcut_dimension_2_code character varying(50),
    dimension_set_entry json,
    business_unit_id bigint,
    source_code character varying(20),
    reason_code character varying(20),
    comment text,
    created_by bigint NOT NULL,
    created_at timestamp(0) without time zone NOT NULL,
    line_status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    posted_entry_id bigint,
    posted_entry_type character varying(255),
    CONSTRAINT general_journal_lines_line_status_check CHECK (((line_status)::text = ANY ((ARRAY['open'::character varying, 'checked'::character varying, 'rejected'::character varying, 'posted'::character varying])::text[])))
);


--
-- Name: general_journal_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.general_journal_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: general_journal_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.general_journal_lines_id_seq OWNED BY public.general_journal_lines.id;


--
-- Name: general_journal_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.general_journal_templates (
    id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    template_type character varying(255) DEFAULT 'general'::character varying NOT NULL,
    number_series_id bigint NOT NULL,
    posting_number_series_id bigint,
    source_code character varying(20),
    reason_code character varying(20),
    default_balancing_account_id bigint,
    force_balancing_account boolean DEFAULT false NOT NULL,
    copy_dimensions_from_batch boolean DEFAULT true NOT NULL,
    suggest_balancing_amount boolean DEFAULT true NOT NULL,
    check_amount_sign boolean DEFAULT true NOT NULL,
    allowed_account_types json,
    mandatory_dimensions json,
    default_dimensions json,
    test_report_before_posting boolean DEFAULT false NOT NULL,
    show_in_role_center boolean DEFAULT true NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT general_journal_templates_template_type_check CHECK (((template_type)::text = ANY ((ARRAY['general'::character varying, 'recurring'::character varying, 'allocation'::character varying])::text[])))
);


--
-- Name: general_journal_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.general_journal_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: general_journal_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.general_journal_templates_id_seq OWNED BY public.general_journal_templates.id;


--
-- Name: general_ledger_setup; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.general_ledger_setup (
    id bigint NOT NULL,
    global_dimension_1_code character varying(20),
    global_dimension_2_code character varying(20),
    shortcut_dimension_3_code character varying(20),
    shortcut_dimension_4_code character varying(20),
    shortcut_dimension_5_code character varying(20),
    shortcut_dimension_6_code character varying(20),
    shortcut_dimension_7_code character varying(20),
    shortcut_dimension_8_code character varying(20),
    lc_code character varying(20),
    company_name character varying(100) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    allow_posting_from date,
    allow_posting_to date,
    retained_earnings_account_id bigint,
    default_expense_offset_account_id bigint
);


--
-- Name: general_ledger_setup_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.general_ledger_setup_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: general_ledger_setup_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.general_ledger_setup_id_seq OWNED BY public.general_ledger_setup.id;


--
-- Name: general_posting_setup_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.general_posting_setup_lines (
    id bigint NOT NULL,
    general_posting_setup_id bigint NOT NULL,
    line_type character varying(255) DEFAULT 'SALES'::character varying NOT NULL,
    chart_of_account_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT general_posting_setup_lines_line_type_check CHECK (((line_type)::text = ANY ((ARRAY['SALES'::character varying, 'SALES_CREDIT_MEMO'::character varying, 'SALES_PREPAYMENT'::character varying, 'PURCHASE'::character varying, 'PURCHASE_CREDIT_MEMO'::character varying, 'PURCHASE_PREPAYMENT'::character varying, 'COGS'::character varying, 'INVENTORY_ADJUSTMENT'::character varying, 'DIRECT_COST_APPLIED'::character varying, 'OVERHEAD_APPLIED'::character varying, 'PURCHASE_VARIANCE'::character varying, 'PRODUCTION_VARIANCE'::character varying])::text[])))
);


--
-- Name: general_posting_setup_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.general_posting_setup_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: general_posting_setup_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.general_posting_setup_lines_id_seq OWNED BY public.general_posting_setup_lines.id;


--
-- Name: general_posting_setups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.general_posting_setups (
    id bigint NOT NULL,
    general_business_posting_group_id bigint CONSTRAINT general_posting_setups_general_business_posting_group__not_null NOT NULL,
    general_product_posting_group_id bigint CONSTRAINT general_posting_setups_general_product_posting_group_i_not_null NOT NULL,
    sales_account_id bigint,
    sales_credit_memo_account_id bigint,
    sales_prepayment_account_id bigint,
    cogs_account_id bigint,
    cogs_credit_memo_account_id bigint,
    cogs_prepayment_account_id bigint,
    inventory_adj_account_id bigint,
    inventory_account_id bigint,
    direct_cost_applied_account_id bigint,
    overhead_applied_account_id bigint,
    purchase_variance_account_id bigint,
    material_variance_account_id bigint,
    capacity_variance_account_id bigint,
    capacity_overhead_variance_account_id bigint,
    manufacturing_overhead_variance_account_id bigint,
    purchase_account_id bigint,
    purchase_credit_memo_account_id bigint,
    blocked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: general_posting_setups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.general_posting_setups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: general_posting_setups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.general_posting_setups_id_seq OWNED BY public.general_posting_setups.id;


--
-- Name: general_product_posting_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.general_product_posting_groups (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(255) NOT NULL,
    default_vat_prod_posting_group character varying(20),
    default_vat_product_posting_group_id bigint,
    auto_create_vat_prod_posting_group boolean DEFAULT false CONSTRAINT general_product_posting_gro_auto_create_vat_prod_posti_not_null NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN general_product_posting_groups.auto_create_vat_prod_posting_group; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.general_product_posting_groups.auto_create_vat_prod_posting_group IS 'Automatically assign the default VAT group when items are created with this group';


--
-- Name: general_product_posting_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.general_product_posting_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: general_product_posting_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.general_product_posting_groups_id_seq OWNED BY public.general_product_posting_groups.id;


--
-- Name: gl_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gl_accounts (
    id bigint NOT NULL,
    account_no character varying(20) NOT NULL,
    account_name character varying(100) NOT NULL,
    account_type character varying(50) NOT NULL,
    account_category character varying(50),
    parent_account_id bigint,
    balance_account_type character varying(20),
    income_balance character varying(20),
    debit_credit character varying(10),
    blocked boolean DEFAULT false NOT NULL,
    direct_posting boolean DEFAULT true NOT NULL,
    reconciliation_account boolean DEFAULT false NOT NULL,
    no_of_blank_lines integer DEFAULT 0 NOT NULL,
    indentation integer DEFAULT 0 NOT NULL,
    totaling character varying(100),
    global_dimension_1_filter character varying(20),
    global_dimension_2_filter character varying(20),
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimension_set_id bigint,
    last_modified_date_time timestamp(0) without time zone,
    created_by bigint,
    last_modified_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: gl_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.gl_accounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gl_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.gl_accounts_id_seq OWNED BY public.gl_accounts.id;


--
-- Name: gl_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gl_entries (
    id bigint NOT NULL,
    entry_number bigint NOT NULL,
    transaction_number bigint NOT NULL,
    chart_of_account_id bigint NOT NULL,
    debit_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    credit_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    amount numeric(15,2) NOT NULL,
    source_type character varying(255) DEFAULT 'CUSTOMER'::character varying NOT NULL,
    source_number character varying(20),
    document_type character varying(30) NOT NULL,
    document_number character varying(20) NOT NULL,
    document_date date NOT NULL,
    posting_date date NOT NULL,
    user_id bigint,
    description character varying(255) NOT NULL,
    comment character varying(255),
    dimensions json,
    reconciled boolean DEFAULT false NOT NULL,
    reconciliation_date date,
    item_ledger_entry_id bigint,
    cust_ledger_entry_id bigint,
    vendor_ledger_entry_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sourceable_type character varying(255),
    sourceable_id bigint,
    debit_amount_lcy numeric(20,4),
    credit_amount_lcy numeric(20,4),
    amount_lcy numeric(20,4),
    currency_id bigint,
    exchange_rate numeric(20,6),
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    is_closing_entry boolean DEFAULT false NOT NULL,
    closing_fiscal_year integer,
    CONSTRAINT gl_entries_source_type_check CHECK (((source_type)::text = ANY ((ARRAY['CUSTOMER'::character varying, 'VENDOR'::character varying, 'ITEM'::character varying, 'BANK'::character varying, 'FIXED_ASSET'::character varying, 'EMPLOYEE'::character varying, 'GENERAL_JOURNAL'::character varying])::text[])))
);


--
-- Name: gl_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.gl_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gl_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.gl_entries_id_seq OWNED BY public.gl_entries.id;


--
-- Name: inventory_adjustment_journals; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventory_adjustment_journals (
    id bigint NOT NULL,
    journal_batch_name character varying(255) NOT NULL,
    description character varying(255),
    posting_date date NOT NULL,
    document_date date,
    status character varying(255) DEFAULT 'Open'::character varying NOT NULL,
    reason_code character varying(255),
    location_code character varying(255),
    assigned_user_id bigint,
    posted_by bigint,
    posted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT inventory_adjustment_journals_status_check CHECK (((status)::text = ANY ((ARRAY['Open'::character varying, 'Released'::character varying, 'Posted'::character varying])::text[])))
);


--
-- Name: inventory_adjustment_journals_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventory_adjustment_journals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_adjustment_journals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_adjustment_journals_id_seq OWNED BY public.inventory_adjustment_journals.id;


--
-- Name: inventory_adjustment_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventory_adjustment_lines (
    id bigint NOT NULL,
    journal_id bigint NOT NULL,
    line_no integer NOT NULL,
    item_id bigint NOT NULL,
    variant_code character varying(255),
    location_code character varying(255),
    bin_code character varying(255),
    quantity numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure_code character varying(255),
    unit_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    quantity_base numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_per_unit_of_measure numeric(18,4) DEFAULT '1'::numeric NOT NULL,
    entry_type character varying(255) DEFAULT 'Positive Adjmt.'::character varying NOT NULL,
    reason_code character varying(255),
    description character varying(255),
    shortcut_dimension_1_code character varying(255),
    shortcut_dimension_2_code character varying(255),
    dimension_set_id bigint,
    applies_to_entry bigint,
    serial_no character varying(255),
    lot_no character varying(255),
    expiration_date date,
    line_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    inventory_posting_group character varying(255),
    gen_bus_posting_group character varying(255),
    gen_prod_posting_group character varying(255),
    quantity_to_handle numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    quantity_to_invoice numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_handled numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_invoiced numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT inventory_adjustment_lines_entry_type_check CHECK (((entry_type)::text = ANY ((ARRAY['Positive Adjmt.'::character varying, 'Negative Adjmt.'::character varying])::text[])))
);


--
-- Name: inventory_adjustment_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventory_adjustment_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_adjustment_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_adjustment_lines_id_seq OWNED BY public.inventory_adjustment_lines.id;


--
-- Name: inventory_posting_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventory_posting_groups (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(255) NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: inventory_posting_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventory_posting_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_posting_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_posting_groups_id_seq OWNED BY public.inventory_posting_groups.id;


--
-- Name: inventory_posting_setups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventory_posting_setups (
    id bigint NOT NULL,
    location_id bigint,
    inventory_posting_group_id bigint NOT NULL,
    inventory_account_id bigint NOT NULL,
    inventory_account_interim_id bigint,
    wip_account_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: inventory_posting_setups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventory_posting_setups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_posting_setups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_posting_setups_id_seq OWNED BY public.inventory_posting_setups.id;


--
-- Name: inventory_putaway_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventory_putaway_lines (
    id bigint NOT NULL,
    inventory_putaway_id bigint NOT NULL,
    line_no integer NOT NULL,
    item_id bigint NOT NULL,
    bin_id bigint,
    quantity numeric(15,4) NOT NULL,
    qty_to_handle numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    qty_handled numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure character varying(20) NOT NULL,
    item_tracking text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: inventory_putaway_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventory_putaway_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_putaway_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_putaway_lines_id_seq OWNED BY public.inventory_putaway_lines.id;


--
-- Name: inventory_putaways; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventory_putaways (
    id bigint NOT NULL,
    no character varying(50) NOT NULL,
    location_id bigint NOT NULL,
    source_document character varying(255) NOT NULL,
    source_no character varying(50) NOT NULL,
    assigned_user_id bigint,
    status character varying(255) DEFAULT 'Open'::character varying NOT NULL,
    posting_date timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT inventory_putaways_source_document_check CHECK (((source_document)::text = ANY ((ARRAY['Purchase Order'::character varying, 'Sales Return'::character varying, 'Inbound Transfer'::character varying, 'Production Output'::character varying, 'Assembly Output'::character varying])::text[]))),
    CONSTRAINT inventory_putaways_status_check CHECK (((status)::text = ANY ((ARRAY['Open'::character varying, 'Pending'::character varying, 'Completed'::character varying])::text[])))
);


--
-- Name: inventory_putaways_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventory_putaways_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_putaways_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_putaways_id_seq OWNED BY public.inventory_putaways.id;


--
-- Name: item_category_assignments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_category_assignments (
    assignment_id bigint NOT NULL,
    item_id bigint NOT NULL,
    category_id bigint NOT NULL,
    is_primary boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: item_category_assignments_assignment_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_category_assignments_assignment_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_category_assignments_assignment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_category_assignments_assignment_id_seq OWNED BY public.item_category_assignments.assignment_id;


--
-- Name: item_charges; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_charges (
    id bigint NOT NULL,
    number character varying(255) NOT NULL,
    description character varying(255),
    description_2 character varying(255),
    gen_prod_posting_group character varying(255),
    vat_prod_posting_group character varying(255),
    search_description character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: item_charges_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_charges_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_charges_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_charges_id_seq OWNED BY public.item_charges.id;


--
-- Name: item_journal_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_journal_batches (
    id bigint NOT NULL,
    template_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    assigned_user_id bigint,
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    location_id bigint,
    default_entry_type character varying(255),
    dimension_filter json,
    reason_code character varying(20),
    copy_item_dimensions boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT item_journal_batches_default_entry_type_check CHECK (((default_entry_type)::text = ANY ((ARRAY['positive_adj'::character varying, 'negative_adj'::character varying, 'purchase'::character varying, 'sale'::character varying, 'transfer'::character varying, 'consumption'::character varying, 'output'::character varying])::text[]))),
    CONSTRAINT item_journal_batches_status_check CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'released'::character varying, 'posted'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: item_journal_batches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_journal_batches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_journal_batches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_journal_batches_id_seq OWNED BY public.item_journal_batches.id;


--
-- Name: item_journal_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_journal_lines (
    id bigint NOT NULL,
    batch_id bigint NOT NULL,
    line_no integer DEFAULT 10000 NOT NULL,
    posting_date date NOT NULL,
    entry_type character varying(255) NOT NULL,
    document_no character varying(50) NOT NULL,
    external_document_no character varying(50),
    item_id bigint NOT NULL,
    variant_code character varying(20),
    description character varying(100),
    unit_of_measure_code character varying(20) NOT NULL,
    quantity numeric(15,4) NOT NULL,
    quantity_base numeric(15,4) NOT NULL,
    location_id bigint NOT NULL,
    zone_id bigint,
    bin_id bigint,
    new_location_id bigint,
    new_zone_id bigint,
    new_bin_id bigint,
    lot_no character varying(50),
    serial_no character varying(50),
    expiration_date date,
    warranty_date date,
    unit_amount numeric(15,4),
    unit_cost numeric(15,4),
    amount numeric(15,4),
    discount_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(10),
    amount_lcy numeric(15,4),
    gen_bus_posting_group_id bigint,
    inventory_posting_group_id bigint,
    dimension_set_entry json,
    source_code character varying(20),
    reason_code character varying(20),
    posted boolean DEFAULT false NOT NULL,
    posted_at timestamp(0) without time zone,
    item_ledger_entry_id bigint,
    created_by bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: item_journal_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_journal_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_journal_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_journal_lines_id_seq OWNED BY public.item_journal_lines.id;


--
-- Name: item_journal_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_journal_templates (
    id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    default_entry_type character varying(255),
    number_series_id bigint NOT NULL,
    posting_number_series_id bigint,
    source_code character varying(20),
    reason_code character varying(20),
    default_inventory_account_id bigint,
    force_inventory_account boolean DEFAULT false NOT NULL,
    item_tracking_mandatory boolean DEFAULT false NOT NULL,
    lot_mandatory boolean DEFAULT false NOT NULL,
    serial_no_mandatory boolean DEFAULT false NOT NULL,
    expiration_date_mandatory boolean DEFAULT false NOT NULL,
    warehouse_location_mandatory boolean DEFAULT false NOT NULL,
    bin_mandatory boolean DEFAULT false NOT NULL,
    check_warehouse_availability boolean DEFAULT true NOT NULL,
    allow_negative_inventory boolean DEFAULT false NOT NULL,
    costing_per_entry boolean DEFAULT false NOT NULL,
    mandatory_dimensions json,
    default_dimensions json,
    allowed_item_categories json,
    blocked_item_nos json,
    test_report_before_posting boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT item_journal_templates_default_entry_type_check CHECK (((default_entry_type)::text = ANY ((ARRAY['positive_adj'::character varying, 'negative_adj'::character varying, 'purchase'::character varying, 'sale'::character varying, 'transfer'::character varying, 'consumption'::character varying, 'output'::character varying])::text[])))
);


--
-- Name: item_journal_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_journal_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_journal_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_journal_templates_id_seq OWNED BY public.item_journal_templates.id;


--
-- Name: item_ledger_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_ledger_entries (
    id bigint NOT NULL,
    entry_number bigint NOT NULL,
    entry_type character varying(255) NOT NULL,
    document_type character varying(30),
    document_number character varying(20) NOT NULL,
    document_line_number integer NOT NULL,
    item_id bigint NOT NULL,
    variant_code character varying(20),
    location_id bigint NOT NULL,
    bin_code character varying(20),
    quantity numeric(15,4) NOT NULL,
    remaining_quantity numeric(15,4) NOT NULL,
    serial_number character varying(50),
    lot_number character varying(50),
    expiration_date date,
    cost_amount_actual numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    cost_amount_expected numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    purchase_amount_actual numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    source_type character varying(255),
    source_id bigint,
    general_business_posting_group_id bigint,
    general_product_posting_group_id bigint NOT NULL,
    inventory_posting_group_id bigint NOT NULL,
    dimensions json,
    posting_date date NOT NULL,
    entry_date timestamp(0) without time zone NOT NULL,
    applied_entry_id bigint,
    open boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT item_ledger_entries_entry_type_check CHECK (((entry_type)::text = ANY ((ARRAY['Purchase'::character varying, 'Sale'::character varying, 'Positive Adjmt.'::character varying, 'Negative Adjmt.'::character varying, 'Transfer'::character varying, 'Consumption'::character varying, 'Output'::character varying, 'Capacity'::character varying, 'Assembly Consumption'::character varying, 'Assembly Output'::character varying, 'Overhead'::character varying])::text[])))
);


--
-- Name: item_ledger_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_ledger_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_ledger_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_ledger_entries_id_seq OWNED BY public.item_ledger_entries.id;


--
-- Name: item_lots; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_lots (
    id bigint NOT NULL,
    item_id bigint NOT NULL,
    lot_number character varying(50) NOT NULL,
    supplier_lot character varying(50),
    receipt_date date NOT NULL,
    expiry_date date NOT NULL,
    retest_date date,
    quantity_received numeric(18,4) NOT NULL,
    quantity_remaining numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    status character varying(255) DEFAULT 'QUARANTINE'::character varying NOT NULL,
    coa_reference character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT item_lots_status_check CHECK (((status)::text = ANY ((ARRAY['QUARANTINE'::character varying, 'APPROVED'::character varying, 'REJECTED'::character varying, 'EXPIRED'::character varying, 'RECALLED'::character varying])::text[])))
);


--
-- Name: item_lots_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_lots_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_lots_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_lots_id_seq OWNED BY public.item_lots.id;


--
-- Name: item_skus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_skus (
    id bigint NOT NULL,
    item_id bigint NOT NULL,
    location_id bigint NOT NULL,
    sku_code character varying(50) NOT NULL,
    reorder_point numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    safety_stock numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    barcode character varying(50),
    lead_time_days integer,
    effective_date date,
    expiry_date date,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: item_skus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_skus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_skus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_skus_id_seq OWNED BY public.item_skus.id;


--
-- Name: item_tracking_codes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_tracking_codes (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    description character varying(255) NOT NULL,
    snspecific_tracking boolean DEFAULT false NOT NULL,
    lotspecific_tracking boolean DEFAULT false NOT NULL,
    lot_wholesale_tracking boolean DEFAULT false NOT NULL,
    man_expiration_date_entry_reqd boolean DEFAULT false NOT NULL,
    man_expiration_date_on_receipt boolean DEFAULT false NOT NULL,
    strict_expiration_posting boolean DEFAULT false NOT NULL,
    allow_expiration_correction boolean DEFAULT false NOT NULL,
    lot_info_purchase_inbound boolean DEFAULT false NOT NULL,
    lot_info_purchase_outbound boolean DEFAULT false NOT NULL,
    lot_info_sales_inbound boolean DEFAULT false NOT NULL,
    lot_info_sales_outbound boolean DEFAULT false NOT NULL,
    sn_info_purchase_inbound boolean DEFAULT false NOT NULL,
    sn_info_purchase_outbound boolean DEFAULT false NOT NULL,
    sn_info_sales_inbound boolean DEFAULT false NOT NULL,
    sn_info_sales_outbound boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN item_tracking_codes.snspecific_tracking; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.item_tracking_codes.snspecific_tracking IS 'SN specific tracking';


--
-- Name: COLUMN item_tracking_codes.lotspecific_tracking; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.item_tracking_codes.lotspecific_tracking IS 'Lot specific tracking';


--
-- Name: COLUMN item_tracking_codes.lot_wholesale_tracking; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.item_tracking_codes.lot_wholesale_tracking IS 'Lot wholesale tracking';


--
-- Name: item_tracking_codes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_tracking_codes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_tracking_codes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_tracking_codes_id_seq OWNED BY public.item_tracking_codes.id;


--
-- Name: item_tracking_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_tracking_lines (
    id bigint NOT NULL,
    source_type character varying(50) NOT NULL,
    source_id bigint NOT NULL,
    source_ref_no character varying(20),
    item_no character varying(20) NOT NULL,
    variant_code character varying(20),
    location_code character varying(20),
    serial_no character varying(50),
    lot_no character varying(50),
    expiration_date date,
    warranty_date date,
    quantity numeric(18,4) NOT NULL,
    quantity_base numeric(18,4) NOT NULL,
    quantity_to_handle numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    quantity_to_invoice numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    quantity_handled numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    quantity_invoiced numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    appl_to_item_entry bigint,
    correction boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: item_tracking_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_tracking_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_tracking_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_tracking_lines_id_seq OWNED BY public.item_tracking_lines.id;


--
-- Name: item_uom_assignments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item_uom_assignments (
    assignment_id bigint NOT NULL,
    item_id bigint NOT NULL,
    uom_id bigint NOT NULL,
    uom_type character varying(30) NOT NULL,
    conversion_factor numeric(15,6) DEFAULT '1'::numeric NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: item_uom_assignments_assignment_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_uom_assignments_assignment_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_uom_assignments_assignment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_uom_assignments_assignment_id_seq OWNED BY public.item_uom_assignments.assignment_id;


--
-- Name: items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.items (
    id bigint NOT NULL,
    item_code character varying(20) NOT NULL,
    description character varying(255) NOT NULL,
    description_2 text,
    item_type character varying(255) DEFAULT 'INVENTORY'::character varying NOT NULL,
    inventory_method character varying(255) DEFAULT 'FIFO'::character varying NOT NULL,
    general_product_posting_group_id bigint NOT NULL,
    inventory_posting_group_id bigint NOT NULL,
    vat_prod_posting_group character varying(20),
    uom_id bigint,
    sku_id bigint,
    vat_id bigint,
    general_posting_setup_id bigint,
    inventory_posting_setup_id bigint,
    vat_product_posting_group_id bigint,
    costing_method character varying(255) DEFAULT 'AVERAGE'::character varying NOT NULL,
    unit_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    standard_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    last_direct_cost numeric(15,4),
    unit_price numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    profit_percent numeric(5,2),
    price_calculation_method character varying(255) DEFAULT 'STANDARD'::character varying NOT NULL,
    default_price_list_code character varying(20),
    allow_negative_price boolean DEFAULT false NOT NULL,
    inventory numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    reorder_point numeric(15,4),
    reorder_quantity numeric(15,4),
    location_id bigint,
    bin_code character varying(20),
    base_uom_id bigint,
    weight numeric(10,4),
    volume numeric(10,4),
    shelf_no character varying(20),
    item_tracking_code character varying(20),
    shelf_life_days integer,
    is_active boolean DEFAULT true NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    sales_blocked boolean DEFAULT false NOT NULL,
    purchasing_blocked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    inventory_bin_id bigint,
    sku character varying(255),
    currency_id bigint,
    item_category_id bigint,
    production_bom_id bigint,
    routing_id bigint,
    CONSTRAINT items_costing_method_check CHECK (((costing_method)::text = ANY ((ARRAY['FIFO'::character varying, 'LIFO'::character varying, 'AVERAGE'::character varying, 'STANDARD'::character varying, 'SPECIFIC'::character varying])::text[]))),
    CONSTRAINT items_inventory_method_check CHECK (((inventory_method)::text = ANY ((ARRAY['FIFO'::character varying, 'LIFO'::character varying, 'AVERAGE'::character varying, 'STANDARD'::character varying])::text[]))),
    CONSTRAINT items_item_type_check CHECK (((item_type)::text = ANY ((ARRAY['RAW_MATERIAL'::character varying, 'FINISHED_GOOD'::character varying, 'PACKAGING'::character varying, 'SPARE_PART'::character varying, 'SERVICE'::character varying])::text[]))),
    CONSTRAINT items_price_calculation_method_check CHECK (((price_calculation_method)::text = ANY ((ARRAY['STANDARD'::character varying, 'COST_PLUS'::character varying, 'PRICE_LIST_ONLY'::character varying])::text[])))
);


--
-- Name: items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.items_id_seq OWNED BY public.items.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: job_journal_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_journal_lines (
    id bigint NOT NULL,
    journal_line_id bigint NOT NULL,
    entry_type character varying(255) NOT NULL,
    job_id bigint NOT NULL,
    job_task_no character varying(50) NOT NULL,
    resource_id bigint,
    item_id bigint,
    gl_account_no character varying(50),
    quantity numeric(15,4) NOT NULL,
    unit_of_measure_code character varying(20) NOT NULL,
    total_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_price numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    chargeable character varying(255) DEFAULT 'Billable'::character varying NOT NULL,
    location_id bigint,
    bin_code character varying(20),
    work_type_code bigint,
    service_order_id bigint,
    description_2 text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT job_journal_lines_chargeable_check CHECK (((chargeable)::text = ANY ((ARRAY['Billable'::character varying, 'Non-Billable'::character varying, 'Both'::character varying])::text[]))),
    CONSTRAINT job_journal_lines_entry_type_check CHECK (((entry_type)::text = ANY ((ARRAY['Resource'::character varying, 'Item'::character varying, 'G/L Account'::character varying])::text[])))
);


--
-- Name: job_journal_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.job_journal_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: job_journal_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.job_journal_lines_id_seq OWNED BY public.job_journal_lines.id;


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: journal_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.journal_batches (
    id bigint NOT NULL,
    journal_template_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    user_id bigint,
    bal_account_type character varying(255),
    bal_account_no character varying(50),
    no_series character varying(50),
    posting_no_series character varying(50),
    reason_code character varying(20),
    recurring boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT journal_batches_bal_account_type_check CHECK (((bal_account_type)::text = ANY ((ARRAY['G/L'::character varying, 'Customer'::character varying, 'Vendor'::character varying, 'Bank'::character varying, 'FixedAsset'::character varying])::text[])))
);


--
-- Name: journal_batches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.journal_batches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: journal_batches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.journal_batches_id_seq OWNED BY public.journal_batches.id;


--
-- Name: journal_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.journal_lines (
    id bigint NOT NULL,
    journal_batch_id bigint NOT NULL,
    line_no integer DEFAULT 10000 NOT NULL,
    posting_date date NOT NULL,
    document_date date,
    document_type character varying(50),
    document_no character varying(50),
    external_document_no character varying(50),
    account_type character varying(255) NOT NULL,
    account_no character varying(50) NOT NULL,
    description text NOT NULL,
    amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    debit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    credit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    bal_account_type character varying(255),
    bal_account_no character varying(50),
    currency_code character varying(10),
    currency_factor numeric(15,8) DEFAULT '1'::numeric NOT NULL,
    amount_lcy numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    dimensions json,
    shortcut_dim_1 character varying(50),
    shortcut_dim_2 character varying(50),
    source_code character varying(20),
    reason_code character varying(20),
    status character varying(255) DEFAULT 'Open'::character varying NOT NULL,
    posted_at timestamp(0) without time zone,
    posted_document_no character varying(50),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT journal_lines_account_type_check CHECK (((account_type)::text = ANY ((ARRAY['G/L'::character varying, 'Customer'::character varying, 'Vendor'::character varying, 'Bank'::character varying, 'FixedAsset'::character varying, 'Item'::character varying, 'Resource'::character varying, 'Job'::character varying])::text[]))),
    CONSTRAINT journal_lines_bal_account_type_check CHECK (((bal_account_type)::text = ANY ((ARRAY['G/L'::character varying, 'Customer'::character varying, 'Vendor'::character varying, 'Bank'::character varying, 'FixedAsset'::character varying])::text[]))),
    CONSTRAINT journal_lines_status_check CHECK (((status)::text = ANY ((ARRAY['Open'::character varying, 'Posted'::character varying, 'Reversed'::character varying])::text[])))
);


--
-- Name: journal_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.journal_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: journal_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.journal_lines_id_seq OWNED BY public.journal_lines.id;


--
-- Name: journal_posting_services; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.journal_posting_services (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: journal_posting_services_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.journal_posting_services_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: journal_posting_services_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.journal_posting_services_id_seq OWNED BY public.journal_posting_services.id;


--
-- Name: journal_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.journal_templates (
    id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100) NOT NULL,
    type character varying(255) NOT NULL,
    recurring boolean DEFAULT false NOT NULL,
    source_code character varying(20),
    no_series character varying(50),
    posting_no_series character varying(50),
    reason_code character varying(20),
    copy_vat_setup_to_lines boolean DEFAULT false NOT NULL,
    allow_vat_difference boolean DEFAULT false NOT NULL,
    bal_account_type character varying(255),
    bal_account_no character varying(50),
    page_id character varying(50),
    test_report_id character varying(50),
    posting_report_id character varying(50),
    copy_to_posted_jnl_lines boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT journal_templates_bal_account_type_check CHECK (((bal_account_type)::text = ANY ((ARRAY['G/L'::character varying, 'Customer'::character varying, 'Vendor'::character varying, 'Bank'::character varying, 'FixedAsset'::character varying])::text[]))),
    CONSTRAINT journal_templates_type_check CHECK (((type)::text = ANY ((ARRAY['General'::character varying, 'Item'::character varying, 'Resource'::character varying, 'FixedAsset'::character varying, 'CashReceipt'::character varying, 'Payment'::character varying, 'Job'::character varying, 'Warehouse'::character varying, 'Recurring'::character varying])::text[])))
);


--
-- Name: journal_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.journal_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: journal_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.journal_templates_id_seq OWNED BY public.journal_templates.id;


--
-- Name: locations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.locations (
    id bigint NOT NULL,
    parent_id bigint,
    code character varying(20) NOT NULL,
    name character varying(255) NOT NULL,
    location_type character varying(30) DEFAULT 'STORAGE'::character varying NOT NULL,
    temperature_zone character varying(30) DEFAULT 'AMBIENT'::character varying NOT NULL,
    address text,
    directed_put_away_and_pick boolean DEFAULT false NOT NULL,
    bin_mandatory boolean DEFAULT false NOT NULL,
    require_receive boolean DEFAULT false NOT NULL,
    require_shipment boolean DEFAULT false NOT NULL,
    require_put_away boolean DEFAULT false NOT NULL,
    require_pick boolean DEFAULT false NOT NULL,
    receipt_bin_code character varying(20),
    shipment_bin_code character varying(20),
    open_shop_floor_bin_code character varying(20),
    inbound_production_bin_code character varying(20),
    outbound_production_bin_code character varying(20),
    adjustment_bin_code character varying(20),
    is_active boolean DEFAULT true NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: locations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.locations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: locations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.locations_id_seq OWNED BY public.locations.id;


--
-- Name: machine_centers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.machine_centers (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    work_center_id bigint NOT NULL,
    capacity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    efficiency numeric(5,2) DEFAULT '100'::numeric NOT NULL,
    direct_unit_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    indirect_cost_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    overhead_rate numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    setup_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    wait_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    move_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    location_code character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    fixed_asset_id bigint,
    operator_employee_id bigint
);


--
-- Name: machine_centers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.machine_centers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: machine_centers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.machine_centers_id_seq OWNED BY public.machine_centers.id;


--
-- Name: maintenance_contract_assets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_contract_assets (
    id bigint NOT NULL,
    maintenance_contract_id bigint NOT NULL,
    fixed_asset_id bigint NOT NULL,
    covered_serial_no character varying(100),
    special_conditions text,
    asset_specific_limit numeric(15,4),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: maintenance_contract_assets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.maintenance_contract_assets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: maintenance_contract_assets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.maintenance_contract_assets_id_seq OWNED BY public.maintenance_contract_assets.id;


--
-- Name: maintenance_contract_billings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_contract_billings (
    id bigint NOT NULL,
    maintenance_contract_id bigint NOT NULL,
    billing_date date NOT NULL,
    amount numeric(15,4) NOT NULL,
    status character varying(255) DEFAULT 'scheduled'::character varying NOT NULL,
    purchase_invoice_id bigint,
    actual_invoice_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT maintenance_contract_billings_status_check CHECK (((status)::text = ANY ((ARRAY['scheduled'::character varying, 'invoiced'::character varying, 'paid'::character varying, 'overdue'::character varying])::text[])))
);


--
-- Name: maintenance_contract_billings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.maintenance_contract_billings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: maintenance_contract_billings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.maintenance_contract_billings_id_seq OWNED BY public.maintenance_contract_billings.id;


--
-- Name: maintenance_contract_schedules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_contract_schedules (
    id bigint NOT NULL,
    maintenance_contract_id bigint NOT NULL,
    fixed_asset_id bigint,
    frequency character varying(255) DEFAULT 'monthly'::character varying NOT NULL,
    interval_months integer DEFAULT 1 NOT NULL,
    first_service_date date NOT NULL,
    last_service_date date,
    next_service_date date NOT NULL,
    service_description text NOT NULL,
    estimated_cost numeric(15,4),
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT maintenance_contract_schedules_frequency_check CHECK (((frequency)::text = ANY ((ARRAY['weekly'::character varying, 'monthly'::character varying, 'quarterly'::character varying, 'semi_annual'::character varying, 'annual'::character varying, 'custom'::character varying])::text[])))
);


--
-- Name: maintenance_contract_schedules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.maintenance_contract_schedules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: maintenance_contract_schedules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.maintenance_contract_schedules_id_seq OWNED BY public.maintenance_contract_schedules.id;


--
-- Name: maintenance_contracts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_contracts (
    id bigint NOT NULL,
    contract_no character varying(50) NOT NULL,
    description character varying(200) NOT NULL,
    external_reference character varying(100),
    contract_type character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    vendor_id bigint NOT NULL,
    responsible_employee_id bigint,
    start_date date NOT NULL,
    end_date date NOT NULL,
    renewal_date date,
    notice_period_days integer DEFAULT 30 NOT NULL,
    auto_renewal boolean DEFAULT false NOT NULL,
    auto_renewal_period_months integer,
    billing_cycle character varying(255) NOT NULL,
    contract_value numeric(15,4) NOT NULL,
    billing_amount numeric(15,4) NOT NULL,
    currency_code character varying(10) DEFAULT 'USD'::character varying NOT NULL,
    hourly_rate numeric(15,4),
    parts_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    max_incidents_per_year integer,
    max_hours_per_year integer,
    max_cost_per_year numeric(15,4),
    deductible_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    response_time_hours_critical integer,
    response_time_hours_standard integer,
    resolution_time_hours integer,
    coverage_type character varying(255) DEFAULT 'specific_assets'::character varying NOT NULL,
    fa_class_id bigint,
    fa_location_id bigint,
    expense_account_id bigint NOT NULL,
    prepaid_account_id bigint,
    accrual_account_id bigint,
    shortcut_dimension_1_code character varying(50),
    shortcut_dimension_2_code character varying(50),
    dimension_set_entry json,
    scope_of_work text,
    exclusions text,
    special_terms text,
    termination_conditions text,
    contract_document_path character varying(255),
    attachments json,
    total_incidents_logged integer DEFAULT 0 NOT NULL,
    total_cost_incurred numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    last_service_date timestamp(0) without time zone,
    next_scheduled_review timestamp(0) without time zone,
    created_by bigint NOT NULL,
    approved_by bigint,
    approved_at timestamp(0) without time zone,
    modified_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT maintenance_contracts_billing_cycle_check CHECK (((billing_cycle)::text = ANY ((ARRAY['monthly'::character varying, 'quarterly'::character varying, 'semi_annual'::character varying, 'annual'::character varying, 'per_incident'::character varying, 'per_hour'::character varying, 'fixed_fee'::character varying])::text[]))),
    CONSTRAINT maintenance_contracts_contract_type_check CHECK (((contract_type)::text = ANY ((ARRAY['preventive'::character varying, 'corrective'::character varying, 'predictive'::character varying, 'full_service'::character varying, 'warranty'::character varying, 'extended_warranty'::character varying])::text[]))),
    CONSTRAINT maintenance_contracts_coverage_type_check CHECK (((coverage_type)::text = ANY ((ARRAY['specific_assets'::character varying, 'asset_category'::character varying, 'location_based'::character varying, 'all_assets'::character varying])::text[]))),
    CONSTRAINT maintenance_contracts_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'active'::character varying, 'expired'::character varying, 'terminated'::character varying, 'renewal_pending'::character varying])::text[])))
);


--
-- Name: maintenance_contracts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.maintenance_contracts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: maintenance_contracts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.maintenance_contracts_id_seq OWNED BY public.maintenance_contracts.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: number_series; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.number_series (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(100) NOT NULL,
    prefix character varying(10) DEFAULT 'P'::character varying NOT NULL,
    starting_number integer DEFAULT 1 NOT NULL,
    ending_number integer,
    current_number integer DEFAULT 0 NOT NULL,
    year integer DEFAULT 2026 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    allow_manual boolean DEFAULT false NOT NULL,
    module character varying(20) DEFAULT 'purchase'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: number_series_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.number_series_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: number_series_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.number_series_id_seq OWNED BY public.number_series.id;


--
-- Name: number_series_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.number_series_lines (
    id bigint NOT NULL,
    number_series_id bigint NOT NULL,
    starting_date date NOT NULL,
    ending_date date,
    prefix character varying(20),
    suffix character varying(20),
    no_of_digits integer DEFAULT 5 NOT NULL,
    starting_no bigint,
    ending_no bigint,
    last_no_used bigint,
    increment_by integer DEFAULT 1 NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: number_series_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.number_series_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: number_series_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.number_series_lines_id_seq OWNED BY public.number_series_lines.id;


--
-- Name: overhead_cost_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.overhead_cost_categories (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: overhead_cost_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.overhead_cost_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: overhead_cost_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.overhead_cost_categories_id_seq OWNED BY public.overhead_cost_categories.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: pay_codes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pay_codes (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    calculation_method character varying(255) NOT NULL,
    default_amount numeric(15,4),
    gl_account_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    default_percentage numeric(5,2),
    taxable boolean DEFAULT true NOT NULL,
    is_statutory boolean DEFAULT false NOT NULL
);


--
-- Name: pay_codes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pay_codes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pay_codes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pay_codes_id_seq OWNED BY public.pay_codes.id;


--
-- Name: payment_applications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payment_applications (
    id bigint NOT NULL,
    payment_id bigint NOT NULL,
    document_type character varying(255) NOT NULL,
    document_id bigint NOT NULL,
    document_number character varying(20) NOT NULL,
    document_original_amount numeric(15,4) NOT NULL,
    document_remaining_before numeric(15,4) NOT NULL,
    amount_applied numeric(15,4) NOT NULL,
    discount_applied numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    write_off_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    document_remaining_after numeric(15,4) NOT NULL,
    full_payment boolean DEFAULT false NOT NULL,
    applied_by bigint NOT NULL,
    applied_at timestamp(0) without time zone NOT NULL,
    reversed boolean DEFAULT false NOT NULL,
    reversed_at timestamp(0) without time zone,
    reversed_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    currency_id bigint,
    amount_applied_lcy numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    gain_loss_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    CONSTRAINT payment_applications_document_type_check CHECK (((document_type)::text = ANY ((ARRAY['SALES_INVOICE'::character varying, 'SALES_CREDIT_MEMO'::character varying, 'PURCHASE_INVOICE'::character varying, 'PURCHASE_CREDIT_MEMO'::character varying])::text[])))
);


--
-- Name: payment_applications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payment_applications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payment_applications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payment_applications_id_seq OWNED BY public.payment_applications.id;


--
-- Name: payment_journal_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payment_journal_lines (
    id bigint NOT NULL,
    journal_line_id bigint NOT NULL,
    vendor_id bigint NOT NULL,
    vendor_no character varying(50),
    amount_paid numeric(15,4) NOT NULL,
    amount_paid_lcy numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    remaining_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    bank_account_id bigint NOT NULL,
    bank_account_no character varying(50),
    applies_to_doc_type character varying(255),
    applies_to_doc_no character varying(50),
    applies_to_id bigint,
    applies_to_amount numeric(15,4),
    payment_method_code character varying(255),
    check_no character varying(50),
    check_date date,
    due_date date,
    exported_to_payment_jnl boolean DEFAULT false NOT NULL,
    payment_processed boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT payment_journal_lines_applies_to_doc_type_check CHECK (((applies_to_doc_type)::text = ANY ((ARRAY['Invoice'::character varying, 'Credit Memo'::character varying, 'Payment'::character varying, 'Refund'::character varying])::text[]))),
    CONSTRAINT payment_journal_lines_payment_method_code_check CHECK (((payment_method_code)::text = ANY ((ARRAY['Cash'::character varying, 'Check'::character varying, 'Bank Transfer'::character varying, 'Credit Card'::character varying, 'Electronic'::character varying])::text[])))
);


--
-- Name: payment_journal_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payment_journal_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payment_journal_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payment_journal_lines_id_seq OWNED BY public.payment_journal_lines.id;


--
-- Name: payment_terms; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payment_terms (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(100) NOT NULL,
    search_description character varying(100),
    calculation_type character varying(30) DEFAULT 'net'::character varying NOT NULL,
    due_date_net_days integer DEFAULT 0 NOT NULL,
    due_date_day_of_month integer,
    due_date_months_ahead integer DEFAULT 0 NOT NULL,
    discount_allowed boolean DEFAULT false NOT NULL,
    discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    discount_calculation_type character varying(30),
    discount_net_days integer DEFAULT 0 NOT NULL,
    payment_tolerance_enabled boolean DEFAULT false NOT NULL,
    payment_tolerance_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    max_payment_tolerance_amount numeric(18,4),
    late_payment_penalty_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    late_payment_grace_days integer DEFAULT 0 NOT NULL,
    discount_account_id bigint,
    payment_tolerance_account_id bigint,
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    is_active boolean DEFAULT true NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    notes text,
    extended_fields json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: payment_terms_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payment_terms_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payment_terms_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payment_terms_id_seq OWNED BY public.payment_terms.id;


--
-- Name: payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payments (
    id bigint NOT NULL,
    payment_number character varying(20) NOT NULL,
    external_reference character varying(50),
    payment_direction character varying(255) NOT NULL,
    party_type character varying(255) NOT NULL,
    party_id bigint NOT NULL,
    party_name character varying(100) NOT NULL,
    payment_method character varying(255) NOT NULL,
    bank_account_id bigint,
    bank_account_number character varying(50),
    check_number character varying(50),
    check_date date,
    counterparty_bank_name character varying(100),
    counterparty_account_number character varying(50),
    counterparty_routing_number character varying(20),
    payment_amount numeric(15,4) NOT NULL,
    applied_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unapplied_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    currency_factor numeric(15,6) DEFAULT '1'::numeric NOT NULL,
    payment_amount_lcy numeric(15,4) NOT NULL,
    discount_taken numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    discount_reason character varying(50),
    transaction_fee numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    transaction_fee_lcy numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    payment_date date NOT NULL,
    posting_date date NOT NULL,
    value_date date,
    clearing_date date,
    status character varying(255) DEFAULT 'PENDING'::character varying NOT NULL,
    reconciled boolean DEFAULT false NOT NULL,
    reconciled_at timestamp(0) without time zone,
    reconciled_by bigint,
    bank_statement_line_id bigint,
    general_business_posting_group_id bigint,
    posting_group_id bigint,
    created_by bigint NOT NULL,
    posted_by bigint,
    posted_at timestamp(0) without time zone,
    voided_at timestamp(0) without time zone,
    voided_by bigint,
    void_reason character varying(200),
    internal_notes text,
    memo text,
    dimensions json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    currency_id bigint,
    CONSTRAINT payments_party_type_check CHECK (((party_type)::text = ANY ((ARRAY['CUSTOMER'::character varying, 'VENDOR'::character varying])::text[]))),
    CONSTRAINT payments_payment_direction_check CHECK (((payment_direction)::text = ANY ((ARRAY['RECEIPT'::character varying, 'DISBURSEMENT'::character varying])::text[]))),
    CONSTRAINT payments_payment_method_check CHECK (((payment_method)::text = ANY ((ARRAY['CASH'::character varying, 'CHECK'::character varying, 'BANK_TRANSFER'::character varying, 'ACH'::character varying, 'WIRE'::character varying, 'CREDIT_CARD'::character varying, 'DEBIT_CARD'::character varying, 'MOBILE_MONEY'::character varying, 'CRYPTO'::character varying, 'OTHER'::character varying])::text[]))),
    CONSTRAINT payments_status_check CHECK (((status)::text = ANY ((ARRAY['PENDING'::character varying, 'POSTED'::character varying, 'CLEARED'::character varying, 'RECONCILED'::character varying, 'VOIDED'::character varying, 'RETURNED'::character varying])::text[])))
);


--
-- Name: payments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payments_id_seq OWNED BY public.payments.id;


--
-- Name: payroll_documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payroll_documents (
    id bigint NOT NULL,
    document_number character varying(20) NOT NULL,
    period_start date NOT NULL,
    period_end date NOT NULL,
    status character varying(255) NOT NULL,
    remarks text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    payroll_period_id bigint,
    total_earnings numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_deductions numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_net_pay numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    approved_by bigint,
    working_days numeric(8,2) DEFAULT '30'::numeric NOT NULL,
    approved_at timestamp(0) without time zone,
    CONSTRAINT payroll_documents_status_check CHECK (((status)::text = ANY (ARRAY[('OPEN'::character varying)::text, ('CALCULATED'::character varying)::text, ('APPROVED'::character varying)::text, ('POSTED'::character varying)::text, ('VOIDED'::character varying)::text])))
);


--
-- Name: payroll_documents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payroll_documents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payroll_documents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payroll_documents_id_seq OWNED BY public.payroll_documents.id;


--
-- Name: payroll_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payroll_lines (
    id bigint NOT NULL,
    payroll_document_id bigint NOT NULL,
    employee_id bigint NOT NULL,
    pay_code_id bigint NOT NULL,
    amount numeric(15,4) NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    line_type character varying(255) DEFAULT 'Earning'::character varying NOT NULL,
    hours numeric(8,2),
    rate numeric(12,4),
    employer_amount numeric(12,2),
    posted_to_g_l boolean DEFAULT false NOT NULL,
    posted_at timestamp(0) without time zone,
    gl_entry_id bigint,
    CONSTRAINT payroll_lines_line_type_check CHECK (((line_type)::text = ANY ((ARRAY['Earning'::character varying, 'Deduction'::character varying, 'Benefit'::character varying])::text[])))
);


--
-- Name: payroll_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payroll_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payroll_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payroll_lines_id_seq OWNED BY public.payroll_lines.id;


--
-- Name: payroll_periods; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payroll_periods (
    id bigint NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    payment_date date NOT NULL,
    status character varying(255) DEFAULT 'OPEN'::character varying NOT NULL,
    is_current boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT payroll_periods_status_check CHECK (((status)::text = ANY ((ARRAY['OPEN'::character varying, 'CLOSED'::character varying, 'POSTED'::character varying])::text[])))
);


--
-- Name: payroll_periods_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payroll_periods_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payroll_periods_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payroll_periods_id_seq OWNED BY public.payroll_periods.id;


--
-- Name: payroll_posting_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payroll_posting_groups (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(255) NOT NULL,
    salaries_account_id bigint NOT NULL,
    wages_account_id bigint,
    social_security_account_id bigint NOT NULL,
    tax_payable_account_id bigint NOT NULL,
    net_pay_account_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: payroll_posting_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payroll_posting_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payroll_posting_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payroll_posting_groups_id_seq OWNED BY public.payroll_posting_groups.id;


--
-- Name: payroll_statutory_setups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payroll_statutory_setups (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    personal_relief numeric(12,2) DEFAULT '2400'::numeric NOT NULL,
    insurance_relief_percentage numeric(5,2) DEFAULT '15'::numeric NOT NULL,
    income_tax_bands json NOT NULL,
    nssf_tier1_limit numeric(12,2) DEFAULT '7000'::numeric NOT NULL,
    nssf_tier1_rate numeric(5,2) DEFAULT '6'::numeric NOT NULL,
    nssf_tier2_limit numeric(12,2) DEFAULT '36000'::numeric NOT NULL,
    nssf_tier2_rate numeric(5,2) DEFAULT '6'::numeric NOT NULL,
    nhif_rate numeric(5,2) DEFAULT 2.75 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: payroll_statutory_setups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payroll_statutory_setups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payroll_statutory_setups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payroll_statutory_setups_id_seq OWNED BY public.payroll_statutory_setups.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: physical_inventory_journals; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.physical_inventory_journals (
    id bigint NOT NULL,
    journal_batch_name character varying(255) NOT NULL,
    description character varying(255),
    posting_date date NOT NULL,
    document_date date,
    status character varying(255) DEFAULT 'Open'::character varying NOT NULL,
    location_code character varying(255),
    bin_code character varying(255),
    reason_code character varying(255),
    assigned_user_id bigint,
    counted_by bigint,
    counted_at timestamp(0) without time zone,
    posted_by bigint,
    posted_at timestamp(0) without time zone,
    sorting_method character varying(255) DEFAULT 'Item'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT physical_inventory_journals_sorting_method_check CHECK (((sorting_method)::text = ANY ((ARRAY['Item'::character varying, 'Bin'::character varying, 'Shelf'::character varying])::text[]))),
    CONSTRAINT physical_inventory_journals_status_check CHECK (((status)::text = ANY ((ARRAY['Open'::character varying, 'Counting'::character varying, 'Calculated'::character varying, 'Posted'::character varying])::text[])))
);


--
-- Name: physical_inventory_journals_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.physical_inventory_journals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: physical_inventory_journals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.physical_inventory_journals_id_seq OWNED BY public.physical_inventory_journals.id;


--
-- Name: physical_inventory_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.physical_inventory_lines (
    id bigint NOT NULL,
    journal_id bigint NOT NULL,
    line_no integer NOT NULL,
    item_id bigint NOT NULL,
    variant_code character varying(255),
    location_code character varying(255),
    bin_code character varying(255),
    shelf_no character varying(255),
    quantity_base numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_physical_inventory numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_calculated numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure_code character varying(255),
    qty_per_unit_of_measure numeric(18,4) DEFAULT '1'::numeric NOT NULL,
    entry_type character varying(255),
    unit_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    item_description character varying(255),
    reason_code character varying(255),
    shortcut_dimension_1_code character varying(255),
    shortcut_dimension_2_code character varying(255),
    dimension_set_id bigint,
    serial_no character varying(255),
    lot_no character varying(255),
    expiration_date date,
    phys_invt_counting_period_code character varying(255),
    phys_invt_counting_period_type character varying(255),
    last_counting_date date,
    next_counting_date date,
    count_frequency_per_year integer,
    inventory_posting_group character varying(255),
    gen_bus_posting_group character varying(255),
    gen_prod_posting_group character varying(255),
    use_item_tracking boolean DEFAULT false NOT NULL,
    qty_to_handle numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_to_invoice numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_handled numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_invoiced numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    no_of_phys_invt_lines integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT physical_inventory_lines_entry_type_check CHECK (((entry_type)::text = ANY ((ARRAY['Positive Adjmt.'::character varying, 'Negative Adjmt.'::character varying])::text[]))),
    CONSTRAINT physical_inventory_lines_phys_invt_counting_period_type_check CHECK (((phys_invt_counting_period_type)::text = ANY ((ARRAY['Item'::character varying, 'SKU'::character varying])::text[])))
);


--
-- Name: COLUMN physical_inventory_lines.quantity_base; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.physical_inventory_lines.quantity_base IS 'Qty on hand from system';


--
-- Name: COLUMN physical_inventory_lines.qty_physical_inventory; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.physical_inventory_lines.qty_physical_inventory IS 'Qty actually counted';


--
-- Name: COLUMN physical_inventory_lines.qty_calculated; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.physical_inventory_lines.qty_calculated IS 'Difference qty';


--
-- Name: physical_inventory_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.physical_inventory_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: physical_inventory_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.physical_inventory_lines_id_seq OWNED BY public.physical_inventory_lines.id;


--
-- Name: posted_purchase_credit_memo_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.posted_purchase_credit_memo_lines (
    id bigint NOT NULL,
    credit_memo_id bigint NOT NULL,
    line_number integer NOT NULL,
    type character varying(255) NOT NULL,
    item_id bigint,
    gl_account_id bigint,
    description text NOT NULL,
    quantity numeric(15,4) NOT NULL,
    unit_of_measure character varying(255),
    unit_price numeric(15,4) NOT NULL,
    discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    amount numeric(15,4) NOT NULL,
    tax_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_total numeric(15,4) NOT NULL,
    general_product_posting_group_id bigint,
    inventory_posting_group_id bigint,
    tax_group_id bigint,
    dimensions json,
    corrected_invoice_line_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: posted_purchase_credit_memo_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.posted_purchase_credit_memo_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: posted_purchase_credit_memo_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.posted_purchase_credit_memo_lines_id_seq OWNED BY public.posted_purchase_credit_memo_lines.id;


--
-- Name: posted_purchase_credit_memos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.posted_purchase_credit_memos (
    id bigint NOT NULL,
    document_number character varying(255) NOT NULL,
    external_document_number character varying(255),
    vendor_invoice_number character varying(255),
    vendor_id bigint NOT NULL,
    vendor_name character varying(255) NOT NULL,
    vendor_address text,
    vendor_city character varying(255),
    vendor_post_code character varying(255),
    vendor_country character varying(255),
    vendor_tax_registration_number character varying(255),
    posting_date date NOT NULL,
    document_date date NOT NULL,
    due_date date,
    vendor_posting_group_id bigint,
    general_business_posting_group_id bigint,
    currency_code character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    currency_factor numeric(15,6) DEFAULT '1'::numeric NOT NULL,
    subtotal numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    grand_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    posted boolean DEFAULT false NOT NULL,
    posted_at timestamp(0) without time zone,
    posted_by bigint,
    source_document_id bigint,
    source_document_type character varying(255),
    corrects_invoice_number character varying(255),
    corrects_invoice_id bigint,
    payment_terms_code character varying(255),
    dimensions json,
    reason_code character varying(255),
    description text,
    location_code character varying(255),
    warehouse_receipt_number character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    currency_id bigint
);


--
-- Name: posted_purchase_credit_memos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.posted_purchase_credit_memos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: posted_purchase_credit_memos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.posted_purchase_credit_memos_id_seq OWNED BY public.posted_purchase_credit_memos.id;


--
-- Name: posted_purchase_invoice_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.posted_purchase_invoice_lines (
    id bigint NOT NULL,
    posted_purchase_invoice_id bigint CONSTRAINT posted_purchase_invoice_lin_posted_purchase_invoice_id_not_null NOT NULL,
    po_line_id bigint,
    po_line_number integer,
    item_id bigint,
    item_code character varying(20),
    item_description character varying(100) NOT NULL,
    variant_code character varying(20),
    general_product_posting_group_id bigint,
    inventory_posting_group_id bigint,
    gl_account_id bigint,
    gl_account_number character varying(20),
    gl_account_name character varying(100),
    quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure_code character varying(20),
    qty_per_unit_of_measure numeric(10,4) DEFAULT '1'::numeric NOT NULL,
    quantity_base numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_cost_lcy numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_code character varying(20),
    vat_percentage numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    vat_amount_lcy numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    amount_including_vat numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    amount_including_vat_lcy numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    lot_number character varying(50),
    serial_number character varying(50),
    expiration_date date,
    dimensions json,
    item_ledger_entry_id bigint,
    gl_entry_id bigint,
    line_number integer DEFAULT 0 NOT NULL,
    posting_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: posted_purchase_invoice_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.posted_purchase_invoice_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: posted_purchase_invoice_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.posted_purchase_invoice_lines_id_seq OWNED BY public.posted_purchase_invoice_lines.id;


--
-- Name: posted_purchase_invoices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.posted_purchase_invoices (
    id bigint NOT NULL,
    document_number character varying(20) NOT NULL,
    external_document_number character varying(50),
    order_id bigint,
    order_number character varying(20),
    vendor_id bigint NOT NULL,
    vendor_name character varying(100) NOT NULL,
    vendor_address character varying(200),
    general_business_posting_group_id bigint,
    vendor_posting_group_id bigint,
    vat_business_posting_group_id bigint,
    location_id bigint,
    posting_date date NOT NULL,
    document_date date NOT NULL,
    due_date date NOT NULL,
    vat_date date,
    total_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_vat numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    grand_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    currency_factor numeric(15,6) DEFAULT '1'::numeric NOT NULL,
    amount_paid numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    remaining_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    paid_in_full boolean DEFAULT false NOT NULL,
    paid_in_full_date timestamp(0) without time zone,
    posted_by bigint,
    posted_at timestamp(0) without time zone,
    cancelled boolean DEFAULT false NOT NULL,
    cancelled_at timestamp(0) without time zone,
    cancelled_by bigint,
    cancellation_reason character varying(200),
    corrective_document_number character varying(20),
    dimensions json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: posted_purchase_invoices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.posted_purchase_invoices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: posted_purchase_invoices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.posted_purchase_invoices_id_seq OWNED BY public.posted_purchase_invoices.id;


--
-- Name: posted_sales_credit_memo_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.posted_sales_credit_memo_lines (
    id bigint NOT NULL,
    posted_sales_credit_memo_id bigint CONSTRAINT posted_sales_credit_memo_li_posted_sales_credit_memo_i_not_null NOT NULL,
    corrected_invoice_line_id bigint,
    so_line_id bigint,
    so_line_number integer,
    item_id bigint,
    item_code character varying(20),
    item_description character varying(100) NOT NULL,
    variant_code character varying(20),
    general_product_posting_group_id bigint,
    inventory_posting_group_id bigint,
    sales_account_id bigint,
    cogs_account_id bigint,
    inventory_account_id bigint,
    returns_account_id bigint,
    quantity numeric(15,4) NOT NULL,
    unit_of_measure_code character varying(20) NOT NULL,
    qty_per_unit_of_measure numeric(10,4) DEFAULT '1'::numeric NOT NULL,
    quantity_base numeric(15,4) NOT NULL,
    unit_price numeric(15,4) NOT NULL,
    unit_cost numeric(15,4),
    unit_cost_lcy numeric(15,4),
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_total numeric(15,4) NOT NULL,
    line_amount numeric(15,4) NOT NULL,
    vat_code character varying(20),
    vat_percentage numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    amount_including_vat numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    cost_amount_reversed numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    inventory_amount_reversed numeric(15,4) DEFAULT '0'::numeric CONSTRAINT posted_sales_credit_memo_lin_inventory_amount_reversed_not_null NOT NULL,
    return_type character varying(255) DEFAULT 'FULL'::character varying NOT NULL,
    lot_number character varying(50),
    serial_number character varying(50),
    expiration_date date,
    posting_date date NOT NULL,
    warehouse_receipt_id bigint,
    return_bin_code character varying(20),
    item_ledger_entry_id bigint,
    gl_entry_id bigint,
    dimensions json,
    line_number integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT posted_sales_credit_memo_lines_return_type_check CHECK (((return_type)::text = ANY ((ARRAY['FULL'::character varying, 'PARTIAL'::character varying, 'DAMAGED'::character varying, 'DEFECTIVE'::character varying, 'WRONG_ITEM'::character varying])::text[])))
);


--
-- Name: posted_sales_credit_memo_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.posted_sales_credit_memo_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: posted_sales_credit_memo_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.posted_sales_credit_memo_lines_id_seq OWNED BY public.posted_sales_credit_memo_lines.id;


--
-- Name: posted_sales_credit_memos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.posted_sales_credit_memos (
    id bigint NOT NULL,
    document_number character varying(20) NOT NULL,
    external_document_number character varying(50),
    corrected_invoice_id bigint,
    corrected_invoice_number character varying(20),
    order_id bigint,
    order_number character varying(20),
    customer_id bigint NOT NULL,
    customer_name character varying(100) NOT NULL,
    customer_address character varying(200),
    ship_to_name character varying(100),
    ship_to_address character varying(200),
    general_business_posting_group_id bigint,
    customer_posting_group_id bigint,
    vat_bus_posting_group character varying(20),
    location_id bigint,
    credit_memo_type character varying(255) DEFAULT 'RETURN'::character varying NOT NULL,
    return_reason_code character varying(20),
    return_reason_comment text,
    posting_date date NOT NULL,
    document_date date NOT NULL,
    vat_date date,
    subtotal numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_discount_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_vat numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    grand_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    currency_factor numeric(15,6) DEFAULT '1'::numeric NOT NULL,
    amount_applied numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    remaining_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    fully_applied boolean DEFAULT false NOT NULL,
    fully_applied_date timestamp(0) without time zone,
    refunded boolean DEFAULT false NOT NULL,
    refund_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    refunded_at timestamp(0) without time zone,
    refund_reference character varying(50),
    posted_by bigint NOT NULL,
    posted_at timestamp(0) without time zone NOT NULL,
    salesperson_id bigint,
    corrected boolean DEFAULT false NOT NULL,
    corrected_at timestamp(0) without time zone,
    correcting_document_number character varying(20),
    dimensions json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    currency_id bigint,
    CONSTRAINT posted_sales_credit_memos_credit_memo_type_check CHECK (((credit_memo_type)::text = ANY ((ARRAY['RETURN'::character varying, 'ALLOWANCE'::character varying, 'CORRECTION'::character varying, 'WRITE_OFF'::character varying, 'REBATE'::character varying])::text[])))
);


--
-- Name: posted_sales_credit_memos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.posted_sales_credit_memos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: posted_sales_credit_memos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.posted_sales_credit_memos_id_seq OWNED BY public.posted_sales_credit_memos.id;


--
-- Name: posted_sales_invoice_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.posted_sales_invoice_lines (
    id bigint NOT NULL,
    posted_sales_invoice_id bigint NOT NULL,
    so_line_id bigint,
    so_line_number integer,
    item_id bigint,
    item_code character varying(20),
    item_description character varying(100) NOT NULL,
    variant_code character varying(20),
    posting_date date NOT NULL,
    general_product_posting_group_id bigint,
    inventory_posting_group_id bigint,
    sales_account_id bigint,
    cogs_account_id bigint,
    inventory_account_id bigint,
    quantity numeric(15,4) NOT NULL,
    unit_of_measure_code character varying(20) NOT NULL,
    qty_per_unit_of_measure numeric(10,4) DEFAULT '1'::numeric NOT NULL,
    quantity_base numeric(15,4) NOT NULL,
    unit_price numeric(15,4) NOT NULL,
    unit_cost numeric(15,4),
    unit_cost_lcy numeric(15,4),
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_total numeric(15,4) NOT NULL,
    line_amount numeric(15,4) NOT NULL,
    vat_code character varying(20),
    vat_percentage numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    amount_including_vat numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    cost_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    profit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    lot_number character varying(50),
    serial_number character varying(50),
    expiration_date date,
    item_ledger_entry_id bigint,
    shipment_id bigint,
    dimensions json,
    line_number integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: posted_sales_invoice_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.posted_sales_invoice_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: posted_sales_invoice_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.posted_sales_invoice_lines_id_seq OWNED BY public.posted_sales_invoice_lines.id;


--
-- Name: posted_sales_invoices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.posted_sales_invoices (
    id bigint NOT NULL,
    document_number character varying(20) NOT NULL,
    external_document_number character varying(50),
    order_id bigint,
    order_number character varying(20),
    customer_id bigint NOT NULL,
    customer_name character varying(100) NOT NULL,
    customer_address character varying(200),
    ship_to_name character varying(100),
    ship_to_address character varying(200),
    general_business_posting_group_id bigint,
    customer_posting_group_id bigint,
    vat_bus_posting_group character varying(20),
    location_id bigint,
    shipping_agent_code character varying(20),
    posting_date date NOT NULL,
    document_date date NOT NULL,
    due_date date NOT NULL,
    vat_date date,
    shipment_date date,
    subtotal numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_discount_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    invoice_discount_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_vat numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    grand_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    currency_factor numeric(15,6) DEFAULT '1'::numeric NOT NULL,
    amount_paid numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    remaining_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    paid_in_full boolean DEFAULT false NOT NULL,
    paid_in_full_date timestamp(0) without time zone,
    posted_by bigint NOT NULL,
    posted_at timestamp(0) without time zone NOT NULL,
    salesperson_id bigint,
    cancelled boolean DEFAULT false NOT NULL,
    cancelled_at timestamp(0) without time zone,
    cancelled_by bigint,
    corrective_document_number character varying(20),
    dimensions json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    currency_id bigint
);


--
-- Name: posted_sales_invoices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.posted_sales_invoices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: posted_sales_invoices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.posted_sales_invoices_id_seq OWNED BY public.posted_sales_invoices.id;


--
-- Name: price_change_template_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.price_change_template_lines (
    id bigint CONSTRAINT price_change_template_items_id_not_null NOT NULL,
    template_id bigint CONSTRAINT price_change_template_items_template_id_not_null NOT NULL,
    item_id bigint,
    category_id bigint,
    current_unit_price numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    new_unit_price numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    adjustment_percent numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    adjustment_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    applied_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    business_id bigint,
    customer_group_id bigint
);


--
-- Name: price_change_template_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.price_change_template_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: price_change_template_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.price_change_template_items_id_seq OWNED BY public.price_change_template_lines.id;


--
-- Name: price_change_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.price_change_templates (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    adjustment_type character varying(255) NOT NULL,
    value numeric(10,2) NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    effective_from timestamp(0) without time zone,
    effective_to timestamp(0) without time zone,
    base character varying(255) NOT NULL,
    rounding numeric(10,2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT price_change_templates_adjustment_type_check CHECK (((adjustment_type)::text = ANY ((ARRAY['increase'::character varying, 'decrease'::character varying, 'fixed'::character varying])::text[]))),
    CONSTRAINT price_change_templates_base_check CHECK (((base)::text = ANY ((ARRAY['cost'::character varying, 'price'::character varying])::text[]))),
    CONSTRAINT price_change_templates_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'approved'::character varying, 'applied'::character varying])::text[])))
);


--
-- Name: price_change_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.price_change_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: price_change_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.price_change_templates_id_seq OWNED BY public.price_change_templates.id;


--
-- Name: price_lists; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.price_lists (
    id bigint NOT NULL,
    item_id bigint NOT NULL,
    customer_id bigint,
    customer_group_id bigint,
    price numeric(18,2) NOT NULL,
    currency character varying(255) DEFAULT 'NGN'::character varying NOT NULL,
    starting_date date NOT NULL,
    ending_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: price_lists_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.price_lists_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: price_lists_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.price_lists_id_seq OWNED BY public.price_lists.id;


--
-- Name: pricing_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pricing_groups (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    pricing_strategy character varying(255) DEFAULT 'STANDARD'::character varying NOT NULL,
    default_discount_percent numeric(5,2),
    default_markup_percent numeric(5,2),
    allow_manual_override boolean DEFAULT true NOT NULL,
    enforce_minimum_margin boolean DEFAULT false NOT NULL,
    minimum_margin_percent numeric(5,2),
    currency_code character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    start_date date,
    end_date date,
    general_business_posting_group_id bigint,
    blocked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT pricing_groups_pricing_strategy_check CHECK (((pricing_strategy)::text = ANY ((ARRAY['STANDARD'::character varying, 'TIERED'::character varying, 'DYNAMIC'::character varying, 'COST_PLUS'::character varying, 'DISCOUNT_PERCENT'::character varying, 'DISCOUNT_AMOUNT'::character varying])::text[])))
);


--
-- Name: pricing_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pricing_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pricing_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pricing_groups_id_seq OWNED BY public.pricing_groups.id;


--
-- Name: pricing_master; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pricing_master (
    id bigint NOT NULL,
    price_list_code character varying(20) NOT NULL,
    description character varying(255),
    price_list_type character varying(255) DEFAULT 'ALL_CUSTOMERS'::character varying NOT NULL,
    customer_id bigint,
    pricing_group_id bigint,
    item_id bigint,
    variant_code character varying(20),
    unit_of_measure_code character varying(20),
    location_id bigint,
    currency_code character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    price_type character varying(255) DEFAULT 'UNIT_PRICE'::character varying NOT NULL,
    unit_price numeric(15,4),
    discount_percent numeric(5,2),
    discount_amount numeric(15,4),
    cost_plus_percent numeric(5,2),
    minimum_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    maximum_quantity numeric(15,4),
    allow_quantity_breaks boolean DEFAULT false NOT NULL,
    start_date date NOT NULL,
    end_date date,
    start_time time(0) without time zone,
    end_time time(0) without time zone,
    applicable_days json,
    minimum_order_amount numeric(15,2),
    minimum_order_quantity numeric(15,4),
    minimum_lead_time_days integer,
    status character varying(255) DEFAULT 'DRAFT'::character varying NOT NULL,
    approved_by bigint NOT NULL,
    approved_at timestamp(0) without time zone,
    created_by character varying(255) NOT NULL,
    modified_by character varying(255),
    modification_reason text,
    priority integer DEFAULT 0 NOT NULL,
    is_current_version boolean DEFAULT true NOT NULL,
    replaces_id bigint,
    replaced_by_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT pricing_master_price_list_type_check CHECK (((price_list_type)::text = ANY ((ARRAY['CUSTOMER'::character varying, 'CUSTOMER_GROUP'::character varying, 'ALL_CUSTOMERS'::character varying, 'CAMPAIGN'::character varying, 'TRANSFER'::character varying])::text[]))),
    CONSTRAINT pricing_master_price_type_check CHECK (((price_type)::text = ANY ((ARRAY['UNIT_PRICE'::character varying, 'PERCENT_DISCOUNT'::character varying, 'AMOUNT_DISCOUNT'::character varying, 'COST_PLUS_PERCENT'::character varying, 'COST_PLUS_AMOUNT'::character varying, 'FORMULA'::character varying])::text[]))),
    CONSTRAINT pricing_master_status_check CHECK (((status)::text = ANY ((ARRAY['DRAFT'::character varying, 'PENDING_APPROVAL'::character varying, 'ACTIVE'::character varying, 'EXPIRED'::character varying, 'CANCELLED'::character varying])::text[])))
);


--
-- Name: pricing_master_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pricing_master_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pricing_master_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pricing_master_id_seq OWNED BY public.pricing_master.id;


--
-- Name: pricing_master_quantity_breaks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pricing_master_quantity_breaks (
    id bigint NOT NULL,
    pricing_master_id bigint NOT NULL,
    minimum_quantity numeric(15,4) NOT NULL,
    maximum_quantity numeric(15,4),
    unit_price numeric(15,4),
    discount_percent numeric(5,2),
    discount_amount numeric(15,4),
    unit_of_measure_code character varying(20),
    line_number integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: pricing_master_quantity_breaks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pricing_master_quantity_breaks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pricing_master_quantity_breaks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pricing_master_quantity_breaks_id_seq OWNED BY public.pricing_master_quantity_breaks.id;


--
-- Name: production_bom_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.production_bom_lines (
    id bigint NOT NULL,
    production_bom_id bigint NOT NULL,
    line_number integer NOT NULL,
    type character varying(255) NOT NULL,
    item_id bigint,
    production_bom_id_related bigint,
    description text,
    unit_of_measure_code character varying(255),
    quantity_per numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    scrap_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    routing_link_code character varying(255),
    flushing_method character varying(255),
    "position" character varying(255),
    position_2 character varying(255),
    position_3 character varying(255),
    lead_time_offset_days integer DEFAULT 0 NOT NULL,
    location_code character varying(255),
    bin_code character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: production_bom_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.production_bom_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: production_bom_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.production_bom_lines_id_seq OWNED BY public.production_bom_lines.id;


--
-- Name: production_bom_version_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.production_bom_version_lines (
    id bigint NOT NULL,
    production_bom_version_id bigint NOT NULL,
    line_number integer DEFAULT 10000 NOT NULL,
    type character varying(255) DEFAULT 'ITEM'::character varying NOT NULL,
    item_id bigint,
    production_bom_id_related bigint,
    description character varying(255),
    unit_of_measure_code character varying(255),
    quantity_per numeric(18,4) DEFAULT '1'::numeric NOT NULL,
    scrap_percent numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    routing_link_code character varying(255),
    flushing_method character varying(255),
    "position" character varying(255),
    position_2 character varying(255),
    position_3 character varying(255),
    lead_time_offset_days integer DEFAULT 0 NOT NULL,
    location_code character varying(255),
    bin_code character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: production_bom_version_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.production_bom_version_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: production_bom_version_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.production_bom_version_lines_id_seq OWNED BY public.production_bom_version_lines.id;


--
-- Name: production_bom_versions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.production_bom_versions (
    id bigint NOT NULL,
    production_bom_id bigint NOT NULL,
    version_code character varying(255) NOT NULL,
    description character varying(255),
    status character varying(255) DEFAULT 'UNDER_DEVELOPMENT'::character varying NOT NULL,
    starting_date date,
    ending_date date,
    unit_of_measure_code character varying(255),
    quantity_per numeric(18,4) DEFAULT '1'::numeric NOT NULL,
    cost_rollup numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    created_by bigint,
    last_modified_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: production_bom_versions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.production_bom_versions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: production_bom_versions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.production_bom_versions_id_seq OWNED BY public.production_bom_versions.id;


--
-- Name: production_boms; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.production_boms (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    description character varying(255) NOT NULL,
    item_id bigint,
    unit_of_measure_code character varying(255),
    status character varying(255) DEFAULT 'CERTIFIED'::character varying NOT NULL,
    version character varying(255) DEFAULT '1.0'::character varying NOT NULL,
    starting_date date,
    ending_date date,
    low_level_code integer DEFAULT 0 NOT NULL,
    cost_rollup numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    created_by bigint NOT NULL,
    last_modified_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: production_boms_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.production_boms_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: production_boms_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.production_boms_id_seq OWNED BY public.production_boms.id;


--
-- Name: production_journal_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.production_journal_batches (
    id bigint NOT NULL,
    template_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    assigned_user_id bigint,
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    production_order_id bigint,
    dimension_filter json,
    reason_code character varying(20),
    auto_post_on_release boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT production_journal_batches_status_check CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'released'::character varying, 'posted'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: production_journal_batches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.production_journal_batches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: production_journal_batches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.production_journal_batches_id_seq OWNED BY public.production_journal_batches.id;


--
-- Name: production_journal_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.production_journal_lines (
    id bigint NOT NULL,
    batch_id bigint NOT NULL,
    line_no integer DEFAULT 10000 NOT NULL,
    posting_date date NOT NULL,
    entry_type character varying(255) NOT NULL,
    production_order_id bigint NOT NULL,
    production_order_no character varying(50) NOT NULL,
    routing_line_no integer,
    routing_line_id bigint,
    item_id bigint,
    item_no character varying(50),
    description character varying(100),
    unit_of_measure_code character varying(20),
    quantity numeric(15,4) NOT NULL,
    quantity_base numeric(15,4) NOT NULL,
    location_id bigint,
    zone_id bigint,
    bin_id bigint,
    lot_no character varying(50),
    serial_no character varying(50),
    expiration_date date,
    output_location_id bigint,
    output_bin_id bigint,
    work_center_id bigint,
    machine_center_id bigint,
    setup_time numeric(10,4),
    run_time numeric(10,4),
    stop_time numeric(10,4),
    output_quantity integer,
    scrap_quantity integer,
    direct_cost numeric(15,4),
    overhead_cost numeric(15,4),
    total_cost numeric(15,4),
    unit_cost numeric(15,4),
    flushing_method character varying(255),
    flushed boolean DEFAULT false NOT NULL,
    flushed_at timestamp(0) without time zone,
    wip_account_id bigint,
    inventory_account_id bigint,
    direct_cost_account_id bigint,
    overhead_account_id bigint,
    shortcut_dimension_1_code character varying(50),
    shortcut_dimension_2_code character varying(50),
    dimension_set_entry json,
    source_code character varying(20),
    reason_code character varying(20),
    created_by bigint NOT NULL,
    created_at timestamp(0) without time zone NOT NULL,
    line_status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    item_ledger_entry_id bigint,
    capacity_ledger_entry_id bigint,
    CONSTRAINT production_journal_lines_entry_type_check CHECK (((entry_type)::text = ANY ((ARRAY['consumption'::character varying, 'output'::character varying, 'capacity'::character varying, 'scrap'::character varying])::text[]))),
    CONSTRAINT production_journal_lines_flushing_method_check CHECK (((flushing_method)::text = ANY ((ARRAY['manual'::character varying, 'forward'::character varying, 'backward'::character varying, 'pick'::character varying, 'consume'::character varying])::text[]))),
    CONSTRAINT production_journal_lines_line_status_check CHECK (((line_status)::text = ANY ((ARRAY['open'::character varying, 'checked'::character varying, 'rejected'::character varying, 'posted'::character varying])::text[])))
);


--
-- Name: production_journal_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.production_journal_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: production_journal_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.production_journal_lines_id_seq OWNED BY public.production_journal_lines.id;


--
-- Name: production_journal_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.production_journal_templates (
    id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    journal_type character varying(255) DEFAULT 'consumption'::character varying NOT NULL,
    number_series_id bigint NOT NULL,
    posting_number_series_id bigint,
    source_code character varying(20),
    flushing_method_filter character varying(255) DEFAULT 'all'::character varying NOT NULL,
    allow_flushing_override boolean DEFAULT false NOT NULL,
    auto_post_output boolean DEFAULT false NOT NULL,
    auto_post_consumption boolean DEFAULT false NOT NULL,
    post_capacity boolean DEFAULT true NOT NULL,
    post_time boolean DEFAULT true NOT NULL,
    post_quantity boolean DEFAULT false NOT NULL,
    absorb_overhead boolean DEFAULT true NOT NULL,
    overhead_rate_source character varying(255) DEFAULT 'work_center'::character varying NOT NULL,
    default_wip_account_id bigint,
    force_wip_account boolean DEFAULT false NOT NULL,
    use_production_order_account_setup boolean DEFAULT true CONSTRAINT production_journal_template_use_production_order_accou_not_null NOT NULL,
    mandatory_dimensions json,
    default_dimensions json,
    copy_from_production_order boolean DEFAULT true CONSTRAINT production_journal_template_copy_from_production_order_not_null NOT NULL,
    consolidate_lines boolean DEFAULT true NOT NULL,
    test_report_before_posting boolean DEFAULT true CONSTRAINT production_journal_template_test_report_before_posting_not_null NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT production_journal_templates_flushing_method_filter_check CHECK (((flushing_method_filter)::text = ANY ((ARRAY['manual'::character varying, 'forward'::character varying, 'backward'::character varying, 'pick'::character varying, 'consume'::character varying, 'all'::character varying])::text[]))),
    CONSTRAINT production_journal_templates_journal_type_check CHECK (((journal_type)::text = ANY ((ARRAY['consumption'::character varying, 'output'::character varying, 'capacity'::character varying])::text[]))),
    CONSTRAINT production_journal_templates_overhead_rate_source_check CHECK (((overhead_rate_source)::text = ANY ((ARRAY['work_center'::character varying, 'machine_center'::character varying, 'routing'::character varying])::text[])))
);


--
-- Name: production_journal_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.production_journal_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: production_journal_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.production_journal_templates_id_seq OWNED BY public.production_journal_templates.id;


--
-- Name: production_order_components; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.production_order_components (
    id bigint NOT NULL,
    production_order_id bigint NOT NULL,
    line_number integer NOT NULL,
    item_id bigint NOT NULL,
    description text,
    unit_of_measure_code character varying(255),
    quantity_per numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    expected_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    expected_quantity_base numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    actual_quantity_consumed numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    actual_scrap_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    remaining_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    scrap_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    flushing_method character varying(255) DEFAULT 'MANUAL'::character varying NOT NULL,
    routing_link_code character varying(255),
    location_code character varying(255),
    bin_code character varying(255),
    due_date date,
    reserved_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    bom_level smallint DEFAULT '1'::smallint NOT NULL,
    bom_path character varying(255),
    source_bom_code character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: production_order_components_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.production_order_components_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: production_order_components_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.production_order_components_id_seq OWNED BY public.production_order_components.id;


--
-- Name: production_order_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.production_order_lines (
    id bigint NOT NULL,
    production_order_id bigint NOT NULL,
    line_number integer NOT NULL,
    item_id bigint NOT NULL,
    variant_code character varying(50),
    description character varying(255),
    quantity numeric(18,4) NOT NULL,
    unit_of_measure_code character varying(50) NOT NULL,
    quantity_base numeric(18,4) NOT NULL,
    due_date date,
    starting_date_time timestamp(0) without time zone,
    ending_date_time timestamp(0) without time zone,
    production_bom_id bigint,
    routing_id bigint,
    location_code character varying(50),
    bin_code character varying(50),
    shortcut_dimension_1_code character varying(50),
    shortcut_dimension_2_code character varying(50),
    dimension_set_id json,
    unit_cost numeric(18,4),
    cost_amount numeric(18,4),
    finished boolean DEFAULT false NOT NULL,
    finished_at timestamp(0) without time zone,
    created_by bigint,
    last_modified_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: production_order_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.production_order_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: production_order_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.production_order_lines_id_seq OWNED BY public.production_order_lines.id;


--
-- Name: production_order_routing_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.production_order_routing_lines (
    id bigint NOT NULL,
    production_order_id bigint NOT NULL,
    line_number integer NOT NULL,
    operation_no character varying(255) NOT NULL,
    description text,
    work_center_id bigint,
    machine_center_id bigint,
    setup_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    run_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    wait_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    move_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    setup_time_unit character varying(255) DEFAULT 'MINUTES'::character varying NOT NULL,
    run_time_unit character varying(255) DEFAULT 'MINUTES'::character varying NOT NULL,
    actual_setup_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    actual_run_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    expected_output_quantity numeric(15,4) DEFAULT '0'::numeric CONSTRAINT production_order_routing_line_expected_output_quantity_not_null NOT NULL,
    actual_output_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    scrap_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    starting_date_time timestamp(0) without time zone,
    ending_date_time timestamp(0) without time zone,
    actual_starting_date_time timestamp(0) without time zone,
    actual_ending_date_time timestamp(0) without time zone,
    status character varying(255) DEFAULT 'PLANNED'::character varying NOT NULL,
    routing_link_code character varying(255),
    direct_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    overhead_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: production_order_routing_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.production_order_routing_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: production_order_routing_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.production_order_routing_lines_id_seq OWNED BY public.production_order_routing_lines.id;


--
-- Name: production_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.production_orders (
    id bigint NOT NULL,
    document_number character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'SIMULATED'::character varying NOT NULL,
    source_type character varying(255),
    source_id bigint,
    source_no character varying(255),
    description text,
    item_id bigint NOT NULL,
    variant_code character varying(255),
    quantity numeric(15,4) NOT NULL,
    unit_of_measure_code character varying(255),
    quantity_base numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    due_date date,
    starting_date_time timestamp(0) without time zone,
    ending_date_time timestamp(0) without time zone,
    inventory_posting_group_id bigint,
    general_product_posting_group_id bigint,
    production_bom_id bigint,
    routing_id bigint,
    production_bom_version_id bigint,
    routing_version_id bigint,
    location_code character varying(255),
    bin_code character varying(255),
    shortcut_dimension_1_code character varying(255),
    shortcut_dimension_2_code character varying(255),
    dimension_set_id bigint,
    costing_method character varying(255) DEFAULT 'STANDARD'::character varying NOT NULL,
    unit_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    cost_rollup numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    flushing_method character varying(255) DEFAULT 'MANUAL'::character varying NOT NULL,
    scrap_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    planning_level integer DEFAULT 0 NOT NULL,
    priority integer DEFAULT 0 NOT NULL,
    posted boolean DEFAULT false NOT NULL,
    posted_at timestamp(0) without time zone,
    posted_by bigint,
    finished_at timestamp(0) without time zone,
    finished_by bigint,
    created_by bigint NOT NULL,
    last_modified_by bigint,
    general_business_posting_group_id bigint,
    reserved_from_stock boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    capex_project_id bigint
);


--
-- Name: production_orders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.production_orders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: production_orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.production_orders_id_seq OWNED BY public.production_orders.id;


--
-- Name: purchase_credit_memo_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_credit_memo_lines (
    id bigint NOT NULL,
    purchase_credit_memo_id bigint NOT NULL,
    line_number integer NOT NULL,
    item_id bigint NOT NULL,
    item_code character varying(255),
    description character varying(255),
    quantity numeric(15,4) NOT NULL,
    unit_cost numeric(15,4) NOT NULL,
    line_total numeric(15,4) NOT NULL,
    tax_percent numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    grand_total numeric(15,4) NOT NULL,
    general_product_posting_group_id bigint,
    unit_of_measure_code character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchase_credit_memo_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_credit_memo_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_credit_memo_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_credit_memo_lines_id_seq OWNED BY public.purchase_credit_memo_lines.id;


--
-- Name: purchase_credit_memos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_credit_memos (
    id bigint NOT NULL,
    document_number character varying(255) NOT NULL,
    external_document_number character varying(255),
    vendor_id bigint NOT NULL,
    vendor_name character varying(255) NOT NULL,
    corrects_invoice_id bigint,
    corrects_invoice_number character varying(255),
    subtotal numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    grand_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(255) DEFAULT 'USD'::character varying NOT NULL,
    posting_date date,
    document_date date,
    location_id bigint,
    status character varying(255) DEFAULT 'DRAFT'::character varying NOT NULL,
    rejection_reason character varying(255),
    approver_id bigint,
    approved_at timestamp(0) without time zone,
    created_by bigint,
    reason_code text,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchase_credit_memos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_credit_memos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_credit_memos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_credit_memos_id_seq OWNED BY public.purchase_credit_memos.id;


--
-- Name: purchase_invoice_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_invoice_lines (
    id bigint NOT NULL,
    purchase_invoice_id bigint NOT NULL,
    po_line_id bigint,
    po_line_number integer,
    item_id bigint,
    item_code character varying(20) NOT NULL,
    item_description character varying(100) NOT NULL,
    variant_code character varying(20),
    general_product_posting_group_id bigint,
    inventory_posting_group_id bigint,
    gl_account_id bigint,
    gl_account_number character varying(20),
    gl_account_name character varying(100),
    quantity numeric(15,4) NOT NULL,
    unit_of_measure_code character varying(20) NOT NULL,
    qty_per_unit_of_measure numeric(10,4) DEFAULT '1'::numeric NOT NULL,
    quantity_base numeric(15,4) NOT NULL,
    unit_cost numeric(15,4) NOT NULL,
    unit_cost_lcy numeric(15,4) NOT NULL,
    line_total numeric(15,4) NOT NULL,
    line_discount_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_code character varying(20),
    vat_percentage numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    vat_amount_lcy numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    amount_including_vat numeric(15,4) NOT NULL,
    amount_including_vat_lcy numeric(15,4) NOT NULL,
    lot_number character varying(50),
    serial_number character varying(50),
    expiration_date date,
    job_number character varying(20),
    job_task_number character varying(20),
    dimensions json,
    item_ledger_entry_id bigint,
    gl_entry_id bigint,
    line_number integer NOT NULL,
    posting_date date NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    type character varying(20) DEFAULT 'item'::character varying NOT NULL,
    asset_id bigint,
    fa_posting_type character varying(20)
);


--
-- Name: purchase_invoice_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_invoice_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_invoice_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_invoice_lines_id_seq OWNED BY public.purchase_invoice_lines.id;


--
-- Name: purchase_invoices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_invoices (
    id bigint NOT NULL,
    document_number character varying(20) NOT NULL,
    external_document_number character varying(50),
    order_id bigint,
    order_number character varying(20),
    vendor_id bigint NOT NULL,
    vendor_name character varying(100) NOT NULL,
    vendor_address character varying(200),
    general_business_posting_group_id bigint,
    vendor_posting_group_id bigint,
    vat_business_posting_group_id bigint,
    location_id bigint,
    posting_date date NOT NULL,
    document_date date NOT NULL,
    due_date date NOT NULL,
    vat_date date,
    total_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_vat numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    grand_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    currency_factor numeric(15,6) DEFAULT '1'::numeric NOT NULL,
    amount_paid numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    remaining_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    paid_in_full boolean DEFAULT false NOT NULL,
    paid_in_full_date timestamp(0) without time zone,
    posted_by bigint,
    posted_at timestamp(0) without time zone,
    cancelled boolean DEFAULT false NOT NULL,
    cancelled_at timestamp(0) without time zone,
    cancelled_by bigint,
    cancellation_reason character varying(200),
    corrective_document_number character varying(20),
    dimensions json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    status character varying(20) DEFAULT 'draft'::character varying NOT NULL,
    approved_by bigint,
    approved_at timestamp(0) without time zone,
    rejected_by bigint,
    rejected_at timestamp(0) without time zone
);


--
-- Name: purchase_invoices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_invoices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_invoices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_invoices_id_seq OWNED BY public.purchase_invoices.id;


--
-- Name: purchase_order_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_order_lines (
    id bigint NOT NULL,
    purchase_order_id bigint NOT NULL,
    line_number integer NOT NULL,
    item_id bigint NOT NULL,
    item_code character varying(50) NOT NULL,
    description character varying(255) NOT NULL,
    quantity numeric(15,4) NOT NULL,
    unit_of_measure character varying(20) NOT NULL,
    unit_cost numeric(15,4) NOT NULL,
    line_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    vat_code character varying(20),
    vat_percentage numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    received_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    returned_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    invoiced_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    expected_delivery_date date,
    comment text,
    general_product_posting_group_id bigint,
    variant_code character varying(20),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    type character varying(20) DEFAULT 'item'::character varying NOT NULL,
    asset_id bigint,
    fa_posting_type character varying(20)
);


--
-- Name: purchase_order_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_order_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_order_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_order_lines_id_seq OWNED BY public.purchase_order_lines.id;


--
-- Name: purchase_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_orders (
    id bigint NOT NULL,
    order_number character varying(50) NOT NULL,
    order_type character varying(30) DEFAULT 'purchase_order'::character varying NOT NULL,
    status character varying(20) DEFAULT 'PENDING'::character varying NOT NULL,
    vendor_id bigint NOT NULL,
    vendor_name character varying(255) NOT NULL,
    order_date date NOT NULL,
    location_id bigint NOT NULL,
    posting_date date,
    due_date date,
    delivery_date date,
    payment_terms character varying(50),
    comment text,
    total_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_vat numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    grand_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    created_by bigint NOT NULL,
    approved_by bigint,
    approved_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    general_business_posting_group_id bigint,
    vendor_posting_group_id bigint,
    vat_bus_posting_group character varying(20),
    fully_received_at timestamp(0) without time zone,
    fully_invoiced_at timestamp(0) without time zone,
    is_price_inclusive boolean DEFAULT false NOT NULL,
    currency_code character varying(3) DEFAULT 'USD'::character varying NOT NULL
);


--
-- Name: purchase_orders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_orders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_orders_id_seq OWNED BY public.purchase_orders.id;


--
-- Name: purchase_prices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_prices (
    id bigint NOT NULL,
    vendor_id bigint NOT NULL,
    item_id bigint NOT NULL,
    starting_date date,
    ending_date date,
    minimum_quantity numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    direct_unit_cost numeric(18,4) NOT NULL,
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure_code character varying(10),
    vendor_item_no character varying(20),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchase_prices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_prices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_prices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_prices_id_seq OWNED BY public.purchase_prices.id;


--
-- Name: purchase_quote_approval_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_quote_approval_entries (
    id bigint NOT NULL,
    purchase_quote_id bigint NOT NULL,
    sequence_no integer NOT NULL,
    approver_id bigint NOT NULL,
    status character varying(20) DEFAULT 'created'::character varying NOT NULL,
    approved_at timestamp(0) without time zone,
    approved_by bigint,
    rejected_at timestamp(0) without time zone,
    rejected_by bigint,
    delegated_to bigint,
    delegated_at timestamp(0) without time zone,
    comment text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchase_quote_approval_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_quote_approval_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_quote_approval_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_quote_approval_entries_id_seq OWNED BY public.purchase_quote_approval_entries.id;


--
-- Name: purchase_quote_archives; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_quote_archives (
    id bigint NOT NULL,
    purchase_quote_id bigint NOT NULL,
    version_no integer DEFAULT 1 NOT NULL,
    document_no character varying(20) NOT NULL,
    document_type character varying(20) DEFAULT 'quote'::character varying NOT NULL,
    vendor_id bigint NOT NULL,
    contact_id bigint,
    buyer_id bigint,
    vendor_quote_no character varying(35),
    document_date date NOT NULL,
    posting_date date,
    order_date date,
    due_date date,
    status character varying(20) NOT NULL,
    currency_code character varying(10),
    currency_factor numeric(18,6) DEFAULT '1'::numeric NOT NULL,
    payment_terms_code character varying(10),
    payment_method_code character varying(10),
    location_code character varying(10),
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimensions json,
    amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    amount_including_vat numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    vat_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    vendor_note text,
    internal_note text,
    archived_at timestamp(0) without time zone NOT NULL,
    archived_by bigint NOT NULL,
    archive_reason character varying(50),
    quote_data json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchase_quote_archives_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_quote_archives_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_quote_archives_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_quote_archives_id_seq OWNED BY public.purchase_quote_archives.id;


--
-- Name: purchase_quote_line_archives; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_quote_line_archives (
    id bigint NOT NULL,
    purchase_quote_archive_id bigint NOT NULL,
    line_no integer NOT NULL,
    type character varying(20) NOT NULL,
    no character varying(20),
    variant_code character varying(10),
    description text NOT NULL,
    description_2 text,
    quantity numeric(18,4) NOT NULL,
    unit_of_measure_code character varying(10),
    direct_unit_cost numeric(18,4) NOT NULL,
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    line_amount numeric(18,4) NOT NULL,
    vat_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    amount_including_vat numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    requested_receipt_date date,
    promised_receipt_date date,
    location_code character varying(10),
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimensions json,
    line_data json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchase_quote_line_archives_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_quote_line_archives_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_quote_line_archives_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_quote_line_archives_id_seq OWNED BY public.purchase_quote_line_archives.id;


--
-- Name: purchase_quote_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_quote_lines (
    id bigint NOT NULL,
    purchase_quote_id bigint NOT NULL,
    line_no integer NOT NULL,
    type character varying(20) NOT NULL,
    no character varying(20),
    variant_code character varying(10),
    description text NOT NULL,
    description_2 text,
    quantity numeric(18,4) NOT NULL,
    outstanding_quantity numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure_code character varying(10),
    direct_unit_cost numeric(18,4) NOT NULL,
    unit_cost_lcy numeric(18,4),
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    line_amount numeric(18,4) NOT NULL,
    vat_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    amount_including_vat numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    requested_receipt_date date,
    promised_receipt_date date,
    location_code character varying(10),
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimensions json,
    quantity_to_receive numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    quantity_received numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    purchase_order_line_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchase_quote_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_quote_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_quote_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_quote_lines_id_seq OWNED BY public.purchase_quote_lines.id;


--
-- Name: purchase_quotes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_quotes (
    id bigint NOT NULL,
    document_no character varying(20) NOT NULL,
    document_type character varying(20) DEFAULT 'quote'::character varying NOT NULL,
    vendor_id bigint NOT NULL,
    contact_id bigint,
    buyer_id bigint,
    vendor_quote_no character varying(35),
    document_date date NOT NULL,
    posting_date date,
    order_date date,
    due_date date,
    requested_receipt_date date,
    promised_receipt_date date,
    status character varying(20) DEFAULT 'open'::character varying NOT NULL,
    currency_code character varying(10),
    currency_factor numeric(18,6) DEFAULT '1'::numeric NOT NULL,
    payment_terms_code character varying(10),
    payment_method_code character varying(10),
    location_code character varying(10),
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimensions json,
    amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    amount_including_vat numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    vat_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    vendor_note text,
    internal_note text,
    released_at timestamp(0) without time zone,
    released_by bigint,
    quote_no character varying(20),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    is_price_inclusive boolean DEFAULT false NOT NULL
);


--
-- Name: purchase_quotes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_quotes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_quotes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_quotes_id_seq OWNED BY public.purchase_quotes.id;


--
-- Name: purchase_receipt_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_receipt_lines (
    id bigint NOT NULL,
    purchase_receipt_id bigint NOT NULL,
    line_number integer NOT NULL,
    type character varying(20) NOT NULL,
    no character varying(20),
    description character varying(100) NOT NULL,
    description_2 character varying(50),
    unit_of_measure character varying(10),
    quantity numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    quantity_received numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    quantity_invoiced numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    direct_unit_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    unit_cost_lcy numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    line_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    inv_discount_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    allow_invoice_disc boolean DEFAULT true NOT NULL,
    gross_weight numeric(18,4),
    net_weight numeric(18,4),
    units_per_parcel numeric(18,4),
    unit_volume numeric(18,4),
    appl_to_item_entry integer,
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    item_category_code bigint,
    product_group_code bigint,
    location_code character varying(10),
    bin_code character varying(20),
    expected_receipt_date date,
    planned_receipt_date date,
    requested_receipt_date date,
    promised_receipt_date date,
    purchase_order_id bigint,
    purchase_order_line_id integer,
    prod_order_no character varying(20),
    prod_order_line_no character varying(10),
    job_no character varying(20),
    job_task_no character varying(20),
    job_line_amount numeric(18,4),
    job_line_amount_lcy numeric(18,4),
    job_currency_code character varying(10),
    job_currency_factor character varying(18),
    whse_posting_group character varying(10),
    variant_code character varying(10),
    qty_per_unit_of_measure numeric(18,4),
    unit_of_measure_code character varying(10),
    quantity_base numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_received_base numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_invoiced_base numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    item_charge_base_amount character varying(18),
    correction character varying(255) DEFAULT '0'::character varying NOT NULL,
    cross_reference_no character varying(20),
    cross_reference_type character varying(10),
    cross_reference_type_no character varying(30),
    transaction_type character varying(10),
    transport_method character varying(10),
    attached_to_line_no character varying(10),
    entry_point character varying(10),
    area character varying(10),
    transaction_specification character varying(10),
    tax_area_code character varying(20),
    tax_liable character varying(10),
    tax_group_code character varying(10),
    use_tax numeric(18,4),
    vat_bus_posting_group numeric(18,4),
    vat_prod_posting_group character varying(10),
    vat_base_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    system_created_entry numeric(18,4),
    vat_difference numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    inv_disc_amount_to_invoice numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    prepayment_percent character varying(5) DEFAULT '0'::character varying NOT NULL,
    prepmt_line_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    prepmt_amt_inv numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    prepmt_amt_incl_vat numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    prepayment_vat_difference numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    prepayment_vat_diff_to_deduct numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    prepayment_vat_diff_deducted numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    dimension_set_id character varying(255),
    qty_to_receive character varying(18) DEFAULT '0'::character varying NOT NULL,
    qty_to_invoice character varying(18) DEFAULT '0'::character varying NOT NULL,
    qty_to_assign character varying(18) DEFAULT '0'::character varying NOT NULL,
    qty_assigned character varying(18) DEFAULT '0'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchase_receipt_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_receipt_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_receipt_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_receipt_lines_id_seq OWNED BY public.purchase_receipt_lines.id;


--
-- Name: purchase_receipts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_receipts (
    id bigint NOT NULL,
    document_number character varying(20) NOT NULL,
    external_document_no character varying(35),
    vendor_id bigint NOT NULL,
    vendor_shipment_no character varying(35),
    vendor_invoice_no character varying(35),
    order_address_code character varying(10),
    posting_date date,
    document_date date,
    receiving_location_id bigint,
    buyer_id bigint,
    project_code character varying(20),
    department_code character varying(20),
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimension_set_id bigint,
    purchase_order_id bigint,
    purchase_order_no character varying(20),
    status character varying(20) DEFAULT 'OPEN'::character varying NOT NULL,
    posted boolean DEFAULT false NOT NULL,
    posted_at timestamp(0) without time zone,
    posted_by bigint,
    expected_receipt_date date,
    actual_receipt_date date,
    yours_reference character varying(35),
    our_reference character varying(35),
    transaction_specification character varying(10),
    transport_method character varying(10),
    entry_point character varying(10),
    area character varying(10),
    transaction_type character varying(10),
    language_code character varying(10),
    format_region character varying(20),
    buy_from_vendor_name character varying(100),
    buy_from_address character varying(100),
    buy_from_address_2 character varying(50),
    buy_from_city character varying(30),
    buy_from_post_code character varying(20),
    buy_from_county character varying(30),
    buy_from_country_region_code character varying(10),
    buy_from_contact character varying(100),
    pay_to_vendor_no character varying(20),
    pay_to_name character varying(100),
    pay_to_address character varying(100),
    pay_to_address_2 character varying(50),
    pay_to_city character varying(30),
    pay_to_post_code character varying(20),
    pay_to_county character varying(30),
    pay_to_country_region_code character varying(10),
    pay_to_contact character varying(100),
    ship_to_code character varying(10),
    ship_to_name character varying(100),
    ship_to_address character varying(100),
    ship_to_address_2 character varying(50),
    ship_to_city character varying(30),
    ship_to_post_code character varying(20),
    ship_to_county character varying(30),
    ship_to_country_region_code character varying(10),
    ship_to_contact character varying(100),
    location_code character varying(10),
    shipment_method_code character varying(10),
    shipping_agent_code character varying(10),
    shipping_agent_service_code character varying(10),
    package_tracking_no character varying(30),
    currency_code character varying(10),
    exchange_rate numeric(19,6),
    prices_including_vat boolean DEFAULT false NOT NULL,
    invoice_disc_code character varying(20),
    comment text,
    requested_receipt_date date,
    promised_receipt_date date,
    created_by bigint,
    last_modified_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: purchase_receipts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_receipts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_receipts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_receipts_id_seq OWNED BY public.purchase_receipts.id;


--
-- Name: putaway_worksheet_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.putaway_worksheet_lines (
    id bigint NOT NULL,
    line_no integer NOT NULL,
    putaway_worksheet_id bigint NOT NULL,
    warehouse_receipt_id bigint NOT NULL,
    item_id bigint NOT NULL,
    quantity numeric(15,4) NOT NULL,
    qty_to_handle numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    source_no character varying(50) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    bin_id bigint
);


--
-- Name: putaway_worksheet_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.putaway_worksheet_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: putaway_worksheet_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.putaway_worksheet_lines_id_seq OWNED BY public.putaway_worksheet_lines.id;


--
-- Name: putaway_worksheets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.putaway_worksheets (
    id bigint NOT NULL,
    worksheet_number character varying(50) NOT NULL,
    location_id bigint NOT NULL,
    user_id bigint NOT NULL,
    status character varying(255) DEFAULT 'Open'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT putaway_worksheets_status_check CHECK (((status)::text = ANY ((ARRAY['Open'::character varying, 'Released'::character varying, 'In Progress'::character varying, 'Completed'::character varying])::text[])))
);


--
-- Name: putaway_worksheets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.putaway_worksheets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: putaway_worksheets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.putaway_worksheets_id_seq OWNED BY public.putaway_worksheets.id;


--
-- Name: reason_codes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reason_codes (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    description character varying(255) NOT NULL,
    default_location_code character varying(255),
    default_bin_code character varying(255),
    inventory_adjustment_account character varying(255),
    inventory_account character varying(255),
    blocked boolean DEFAULT false NOT NULL,
    comment text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN reason_codes.inventory_adjustment_account; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.reason_codes.inventory_adjustment_account IS 'G/L Account for adjustment posting';


--
-- Name: COLUMN reason_codes.inventory_account; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.reason_codes.inventory_account IS 'G/L Account for inventory';


--
-- Name: reason_codes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reason_codes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reason_codes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reason_codes_id_seq OWNED BY public.reason_codes.id;


--
-- Name: recurring_expenses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recurring_expenses (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(255),
    vendor_id bigint,
    category_id bigint,
    category_code character varying(255),
    amount numeric(15,4) NOT NULL,
    currency_id bigint,
    frequency character varying(255) NOT NULL,
    start_date date NOT NULL,
    end_date date,
    last_occurrence_at date,
    next_occurrence_at date NOT NULL,
    "interval" integer DEFAULT 1 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    auto_post boolean DEFAULT false NOT NULL,
    dimension_set_id bigint,
    shortcut_dimension_1_code character varying(255),
    shortcut_dimension_2_code character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: COLUMN recurring_expenses.frequency; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.recurring_expenses.frequency IS 'daily, weekly, monthly, quarterly, yearly';


--
-- Name: recurring_expenses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recurring_expenses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recurring_expenses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recurring_expenses_id_seq OWNED BY public.recurring_expenses.id;


--
-- Name: recurring_journal_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recurring_journal_batches (
    id bigint NOT NULL,
    template_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    assigned_user_id bigint,
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    current_processing_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT recurring_journal_batches_status_check CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'processing'::character varying, 'posted'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: recurring_journal_batches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recurring_journal_batches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recurring_journal_batches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recurring_journal_batches_id_seq OWNED BY public.recurring_journal_batches.id;


--
-- Name: recurring_journal_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recurring_journal_lines (
    id bigint NOT NULL,
    batch_id bigint NOT NULL,
    line_no integer DEFAULT 10000 NOT NULL,
    recurring_method character varying(255) NOT NULL,
    starting_date date NOT NULL,
    ending_date date,
    expiration_date date,
    posting_date date,
    account_id bigint NOT NULL,
    account_type character varying(20) DEFAULT 'gl'::character varying NOT NULL,
    balancing_account_id bigint,
    description text NOT NULL,
    amount numeric(15,4),
    calculation_formula character varying(255),
    account_to_calculate_balance character varying(50),
    percentage_for_balance numeric(5,2),
    shortcut_dimension_1_code character varying(50),
    shortcut_dimension_2_code character varying(50),
    dimension_set_entry json,
    use_allocation boolean DEFAULT false NOT NULL,
    allocation_id bigint,
    source_code character varying(20),
    reason_code character varying(20),
    created_by bigint NOT NULL,
    created_at timestamp(0) without time zone NOT NULL,
    last_posting_date date,
    next_posting_date date,
    posting_count integer DEFAULT 0 NOT NULL,
    line_status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    CONSTRAINT recurring_journal_lines_line_status_check CHECK (((line_status)::text = ANY ((ARRAY['active'::character varying, 'expired'::character varying, 'on_hold'::character varying])::text[]))),
    CONSTRAINT recurring_journal_lines_recurring_method_check CHECK (((recurring_method)::text = ANY ((ARRAY['fixed'::character varying, 'variable'::character varying, 'balance'::character varying, 'reversing_fixed'::character varying, 'reversing_variable'::character varying, 'reversing_balance'::character varying])::text[])))
);


--
-- Name: recurring_journal_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recurring_journal_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recurring_journal_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recurring_journal_lines_id_seq OWNED BY public.recurring_journal_lines.id;


--
-- Name: recurring_journal_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recurring_journal_templates (
    id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    number_series_id bigint NOT NULL,
    posting_number_series_id bigint,
    source_code character varying(20),
    recurring_method character varying(255) DEFAULT 'fixed'::character varying NOT NULL,
    recurring_frequency character varying(20) DEFAULT 'monthly'::character varying NOT NULL,
    recurring_interval integer DEFAULT 1 NOT NULL,
    start_date date NOT NULL,
    end_date date,
    auto_post boolean DEFAULT false NOT NULL,
    last_posting_date timestamp(0) without time zone,
    next_posting_date timestamp(0) without time zone,
    calculation_formula text,
    fixed_amount numeric(15,4),
    auto_reverse boolean DEFAULT false NOT NULL,
    reversal_days_offset integer DEFAULT 1 NOT NULL,
    default_balancing_account_id bigint,
    mandatory_dimensions json,
    default_dimensions json,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT recurring_journal_templates_recurring_method_check CHECK (((recurring_method)::text = ANY ((ARRAY['fixed'::character varying, 'variable'::character varying, 'balance'::character varying, 'reversing_fixed'::character varying, 'reversing_variable'::character varying, 'reversing_balance'::character varying])::text[])))
);


--
-- Name: recurring_journal_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recurring_journal_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recurring_journal_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recurring_journal_templates_id_seq OWNED BY public.recurring_journal_templates.id;


--
-- Name: reservation_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reservation_entries (
    id bigint NOT NULL,
    entry_no bigint NOT NULL,
    item_no character varying(20) NOT NULL,
    variant_code character varying(20),
    location_code character varying(20) NOT NULL,
    serial_no character varying(50),
    lot_no character varying(50),
    quantity numeric(18,4) NOT NULL,
    quantity_base numeric(18,4) NOT NULL,
    reservation_status character varying(255) DEFAULT 'reservation'::character varying NOT NULL,
    source_type character varying(50) NOT NULL,
    source_id bigint NOT NULL,
    source_ref_no integer,
    source_subtype character varying(20),
    binding_entry_no bigint,
    expected_receipt_date date,
    shipment_date date,
    expiration_date date,
    warranty_date date,
    qty_to_handle numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_to_invoice numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    correction boolean DEFAULT false NOT NULL,
    item_ledg_entry_no bigint,
    planning_level boolean DEFAULT false NOT NULL,
    planning_line_no character varying(20),
    reservation_date timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT reservation_entries_reservation_status_check CHECK (((reservation_status)::text = ANY ((ARRAY['reservation'::character varying, 'tracking'::character varying, 'surplus'::character varying, 'prospect'::character varying])::text[])))
);


--
-- Name: reservation_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reservation_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reservation_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reservation_entries_id_seq OWNED BY public.reservation_entries.id;


--
-- Name: resource_journal_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.resource_journal_lines (
    id bigint NOT NULL,
    journal_line_id bigint NOT NULL,
    entry_type character varying(255) NOT NULL,
    resource_id bigint,
    quantity numeric(15,4) NOT NULL,
    unit_of_measure_code character varying(20) DEFAULT 'HOUR'::character varying NOT NULL,
    direct_unit_cost numeric(15,4),
    unit_cost numeric(15,4),
    total_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_price numeric(15,4),
    total_price numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    job_id bigint,
    job_task_no character varying(50),
    work_type_code bigint,
    chargeable character varying(10) DEFAULT 'Billable'::character varying NOT NULL,
    service_order_id bigint,
    service_item_line_no character varying(50),
    allocation_id bigint,
    time_sheet_description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT resource_journal_lines_entry_type_check CHECK (((entry_type)::text = ANY ((ARRAY['Usage'::character varying, 'Sale'::character varying, 'Purchase'::character varying, 'Charge'::character varying])::text[])))
);


--
-- Name: resource_journal_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.resource_journal_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: resource_journal_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.resource_journal_lines_id_seq OWNED BY public.resource_journal_lines.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: routing_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.routing_lines (
    id bigint NOT NULL,
    routing_id bigint NOT NULL,
    line_number integer NOT NULL,
    operation_no character varying(255) NOT NULL,
    description text,
    work_center_id bigint,
    machine_center_id bigint,
    setup_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    run_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    wait_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    move_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    queue_time numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    fixed_scrap_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    setup_time_unit character varying(255) DEFAULT 'MINUTES'::character varying NOT NULL,
    run_time_unit character varying(255) DEFAULT 'MINUTES'::character varying NOT NULL,
    direct_unit_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    indirect_cost_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    overhead_rate numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    scrap_factor_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    routing_link_code character varying(255),
    subcontractor_id bigint,
    subcontracting_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    concurrent_capacities integer DEFAULT 1 NOT NULL,
    lot_size numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: routing_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.routing_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: routing_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.routing_lines_id_seq OWNED BY public.routing_lines.id;


--
-- Name: routing_version_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.routing_version_lines (
    id bigint NOT NULL,
    routing_version_id bigint NOT NULL,
    line_number integer DEFAULT 10000 NOT NULL,
    operation_no character varying(20),
    description character varying(255),
    work_center_id bigint,
    machine_center_id bigint,
    setup_time numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    run_time numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    wait_time numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    move_time numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    queue_time numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    fixed_scrap_quantity numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    setup_time_unit character varying(255),
    run_time_unit character varying(255),
    direct_unit_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    indirect_cost_percent numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    overhead_rate numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    scrap_factor_percent numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    routing_link_code character varying(255),
    subcontractor_id bigint,
    subcontracting_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    concurrent_capacities integer DEFAULT 1 NOT NULL,
    lot_size numeric(18,4) DEFAULT '1'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: routing_version_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.routing_version_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: routing_version_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.routing_version_lines_id_seq OWNED BY public.routing_version_lines.id;


--
-- Name: routing_versions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.routing_versions (
    id bigint NOT NULL,
    routing_id bigint NOT NULL,
    version_code character varying(20) NOT NULL,
    description character varying(255),
    status character varying(255) DEFAULT 'UNDER_DEVELOPMENT'::character varying NOT NULL,
    type character varying(255) DEFAULT 'SERIAL'::character varying NOT NULL,
    starting_date date,
    ending_date date,
    cost_rollup numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    created_by bigint,
    last_modified_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: routing_versions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.routing_versions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: routing_versions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.routing_versions_id_seq OWNED BY public.routing_versions.id;


--
-- Name: routings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.routings (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    description character varying(255) NOT NULL,
    item_id bigint,
    status character varying(255) DEFAULT 'CERTIFIED'::character varying NOT NULL,
    version character varying(255) DEFAULT '1.0'::character varying NOT NULL,
    starting_date date,
    ending_date date,
    type character varying(255) DEFAULT 'SERIAL'::character varying NOT NULL,
    cost_rollup numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    created_by bigint NOT NULL,
    last_modified_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: routings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.routings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: routings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.routings_id_seq OWNED BY public.routings.id;


--
-- Name: sales_credit_memo_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_credit_memo_lines (
    id bigint NOT NULL,
    sales_credit_memo_id bigint NOT NULL,
    line_no integer DEFAULT 10000 NOT NULL,
    item_id bigint NOT NULL,
    quantity numeric(15,5) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure_code character varying(10),
    unit_price numeric(15,5) DEFAULT '0'::numeric NOT NULL,
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    vat_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    amount_including_vat numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    sales_invoice_line_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sales_credit_memo_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_credit_memo_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_credit_memo_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_credit_memo_lines_id_seq OWNED BY public.sales_credit_memo_lines.id;


--
-- Name: sales_credit_memos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_credit_memos (
    id bigint NOT NULL,
    memo_number character varying(255) NOT NULL,
    total_amount numeric(15,2) NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    reason text,
    effective_date date NOT NULL,
    currency_code character varying(255),
    customer_id bigint NOT NULL,
    posted_by bigint,
    sales_invoice_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT sales_credit_memos_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'pending'::character varying, 'approved'::character varying, 'rejected'::character varying, 'posted'::character varying, 'cancelled'::character varying, 'archived'::character varying])::text[])))
);


--
-- Name: sales_credit_memos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_credit_memos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_credit_memos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_credit_memos_id_seq OWNED BY public.sales_credit_memos.id;


--
-- Name: sales_invoice_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_invoice_lines (
    id bigint NOT NULL,
    sales_invoice_id bigint NOT NULL,
    item_id bigint,
    type character varying(255) DEFAULT 'ITEM'::character varying NOT NULL,
    description character varying(255),
    quantity numeric(18,4) NOT NULL,
    unit_of_measure character varying(255),
    unit_price numeric(18,2) NOT NULL,
    discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    vat_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_amount numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    line_total numeric(18,2) NOT NULL,
    location_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sales_invoice_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_invoice_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_invoice_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_invoice_lines_id_seq OWNED BY public.sales_invoice_lines.id;


--
-- Name: sales_invoices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_invoices (
    id bigint NOT NULL,
    invoice_number character varying(255) NOT NULL,
    customer_id bigint NOT NULL,
    total_amount numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(255) DEFAULT 'NGN'::character varying NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    posted_at timestamp(0) without time zone,
    posted_by bigint,
    approved_by bigint,
    approved_at timestamp(0) without time zone,
    invoice_date date NOT NULL,
    due_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sales_order_id bigint
);


--
-- Name: sales_invoices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_invoices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_invoices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_invoices_id_seq OWNED BY public.sales_invoices.id;


--
-- Name: sales_order_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_order_lines (
    id bigint NOT NULL,
    sales_order_id bigint NOT NULL,
    line_number integer NOT NULL,
    item_id bigint,
    item_code character varying(20),
    description character varying(255) NOT NULL,
    description_2 character varying(100),
    variant_code character varying(20),
    general_product_posting_group_id bigint,
    inventory_posting_group_id bigint,
    quantity numeric(15,4) NOT NULL,
    quantity_shipped numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    quantity_invoiced numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    quantity_to_ship numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure_code character varying(20) NOT NULL,
    qty_per_unit_of_measure numeric(10,4) DEFAULT '1'::numeric NOT NULL,
    quantity_base numeric(15,4) NOT NULL,
    unit_price numeric(15,4) NOT NULL,
    unit_cost numeric(15,4),
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_total numeric(15,4) NOT NULL,
    line_amount numeric(15,4) NOT NULL,
    vat_code character varying(20),
    vat_percentage numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    amount_including_vat numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    price_source character varying(50),
    pricing_master_id bigint,
    planned_delivery_date date,
    requested_delivery_date date,
    promised_delivery_date date,
    reserved_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    reservation_entry_id bigint,
    lot_number character varying(50),
    serial_number character varying(50),
    expiration_date date,
    location_id bigint,
    bin_code character varying(20),
    line_status character varying(255) DEFAULT 'OPEN'::character varying NOT NULL,
    return_against_line_id bigint,
    return_quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    dimensions json,
    comment text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT sales_order_lines_line_status_check CHECK (((line_status)::text = ANY ((ARRAY['OPEN'::character varying, 'PARTIALLY_SHIPPED'::character varying, 'SHIPPED'::character varying, 'INVOICED'::character varying, 'CLOSED'::character varying, 'CANCELLED'::character varying])::text[])))
);


--
-- Name: sales_order_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_order_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_order_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_order_lines_id_seq OWNED BY public.sales_order_lines.id;


--
-- Name: sales_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_orders (
    id bigint NOT NULL,
    order_number character varying(20) NOT NULL,
    external_document_number character varying(50),
    order_type character varying(255) DEFAULT 'SALES_ORDER'::character varying NOT NULL,
    customer_id bigint NOT NULL,
    customer_name character varying(100) NOT NULL,
    customer_address character varying(200),
    ship_to_name character varying(100),
    ship_to_address character varying(200),
    general_business_posting_group_id bigint,
    customer_posting_group_id bigint,
    vat_business_posting_group_id bigint,
    pricing_group_id bigint,
    location_id bigint,
    shipping_agent_code character varying(20),
    shipping_agent_service_code character varying(20),
    shipping_method character varying(255),
    order_date date NOT NULL,
    posting_date date,
    requested_delivery_date date,
    promised_delivery_date date,
    shipment_date date,
    payment_terms_code character varying(20),
    payment_method_code character varying(20),
    subtotal numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_discount_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    invoice_discount_percent numeric(5,2),
    invoice_discount_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    total_vat numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    grand_total numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    currency_factor numeric(15,6) DEFAULT '1'::numeric NOT NULL,
    status character varying(255) DEFAULT 'DRAFT'::character varying NOT NULL,
    quantity_shipped numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    quantity_invoiced numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    fully_shipped boolean DEFAULT false NOT NULL,
    fully_invoiced boolean DEFAULT false NOT NULL,
    salesperson_id bigint,
    assigned_warehouse_worker_id bigint,
    approved_by bigint,
    approved_at timestamp(0) without time zone,
    created_by bigint NOT NULL,
    cancelled_at timestamp(0) without time zone,
    cancelled_by bigint,
    cancellation_reason character varying(200),
    dimensions json,
    internal_comment text,
    customer_comment text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    is_price_inclusive boolean DEFAULT false NOT NULL,
    CONSTRAINT sales_orders_order_type_check CHECK (((order_type)::text = ANY ((ARRAY['SALES_ORDER'::character varying, 'RETURN_ORDER'::character varying, 'REPLACEMENT'::character varying, 'CONTRACT'::character varying])::text[]))),
    CONSTRAINT sales_orders_shipping_method_check CHECK (((shipping_method)::text = ANY ((ARRAY['GROUND'::character varying, 'EXPRESS'::character varying, 'OVERNIGHT'::character varying, 'PICKUP'::character varying, 'FREIGHT'::character varying])::text[]))),
    CONSTRAINT sales_orders_status_check CHECK (((status)::text = ANY ((ARRAY['DRAFT'::character varying, 'PENDING_APPROVAL'::character varying, 'APPROVED'::character varying, 'RELEASED'::character varying, 'PICKING'::character varying, 'PACKED'::character varying, 'SHIPPED'::character varying, 'INVOICED'::character varying, 'PARTIALLY_INVOICED'::character varying, 'CLOSED'::character varying, 'CANCELLED'::character varying])::text[])))
);


--
-- Name: sales_orders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_orders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_orders_id_seq OWNED BY public.sales_orders.id;


--
-- Name: sales_quote_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_quote_items (
    id bigint NOT NULL,
    sales_quote_id bigint NOT NULL,
    item_id bigint NOT NULL,
    quantity numeric(18,4) NOT NULL,
    unit_price numeric(18,2) NOT NULL,
    discount numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    line_total numeric(18,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sales_quote_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_quote_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_quote_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_quote_items_id_seq OWNED BY public.sales_quote_items.id;


--
-- Name: sales_quote_revisions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_quote_revisions (
    id bigint NOT NULL,
    revision_number character varying(20) NOT NULL,
    sales_quote_id bigint NOT NULL,
    changes text NOT NULL,
    description text,
    revision_date timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    version integer DEFAULT 1 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN sales_quote_revisions.revision_number; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sales_quote_revisions.revision_number IS 'Unique revision number';


--
-- Name: COLUMN sales_quote_revisions.description; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sales_quote_revisions.description IS 'Revision description or notes';


--
-- Name: COLUMN sales_quote_revisions.revision_date; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sales_quote_revisions.revision_date IS 'Date of this revision';


--
-- Name: sales_quote_revisions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_quote_revisions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_quote_revisions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_quote_revisions_id_seq OWNED BY public.sales_quote_revisions.id;


--
-- Name: sales_quotes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_quotes (
    id bigint NOT NULL,
    quote_no character varying(255) NOT NULL,
    customer_id bigint NOT NULL,
    quote_date date NOT NULL,
    valid_until date,
    total_amount numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    approval_status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    approved_by bigint,
    approved_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_price_inclusive boolean DEFAULT false NOT NULL
);


--
-- Name: sales_quotes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_quotes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_quotes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_quotes_id_seq OWNED BY public.sales_quotes.id;


--
-- Name: sales_shipment_headers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_shipment_headers (
    id bigint NOT NULL,
    document_no character varying(20) NOT NULL,
    sales_order_id bigint NOT NULL,
    order_no character varying(20),
    sell_to_customer_no character varying(20) NOT NULL,
    sell_to_customer_name character varying(100) NOT NULL,
    sell_to_customer_name_2 character varying(50),
    sell_to_address character varying(100),
    sell_to_address_2 character varying(50),
    sell_to_city character varying(30),
    sell_to_post_code character varying(20),
    sell_to_county character varying(30),
    sell_to_country_region_code character varying(10),
    sell_to_contact character varying(100),
    sell_to_contact_no character varying(20),
    sell_to_phone_no character varying(30),
    sell_to_email character varying(80),
    bill_to_customer_no character varying(20) NOT NULL,
    bill_to_name character varying(100) NOT NULL,
    bill_to_name_2 character varying(50),
    bill_to_address character varying(100),
    bill_to_address_2 character varying(50),
    bill_to_city character varying(30),
    bill_to_post_code character varying(20),
    bill_to_county character varying(30),
    bill_to_country_region_code character varying(10),
    bill_to_contact character varying(100),
    bill_to_contact_no character varying(20),
    ship_to_code character varying(10),
    ship_to_name character varying(100),
    ship_to_name_2 character varying(50),
    ship_to_address character varying(100),
    ship_to_address_2 character varying(50),
    ship_to_city character varying(30),
    ship_to_post_code character varying(20),
    ship_to_county character varying(30),
    ship_to_country_region_code character varying(10),
    ship_to_contact character varying(100),
    ship_to_phone_no character varying(30),
    order_date date NOT NULL,
    posting_date date NOT NULL,
    shipment_date date NOT NULL,
    document_date date NOT NULL,
    due_date date,
    payment_discount_date date,
    requested_delivery_date date,
    promised_delivery_date date,
    shipment_method_code character varying(10),
    shipping_agent_code character varying(10),
    shipping_agent_service_code character varying(10),
    package_tracking_no character varying(50),
    transport_method character varying(10),
    exit_point character varying(10),
    area character varying(10),
    currency_code character varying(10),
    currency_factor numeric(18,6) DEFAULT '1'::numeric NOT NULL,
    customer_posting_group character varying(20) NOT NULL,
    gen_bus_posting_group character varying(20) NOT NULL,
    vat_bus_posting_group character varying(20) NOT NULL,
    tax_area_code character varying(20),
    tax_liable boolean DEFAULT false NOT NULL,
    tax_group_code character varying(20),
    vat_base_discount_pct numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    invoice_disc_code character varying(20),
    customer_disc_group character varying(20),
    customer_price_group character varying(10),
    prices_including_vat boolean DEFAULT false NOT NULL,
    allow_line_disc boolean DEFAULT true NOT NULL,
    payment_terms_code character varying(10),
    payment_method_code character varying(10),
    payment_discount_pct numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    location_code character varying(10),
    responsibility_center character varying(10),
    outbound_whse_handling_time integer,
    shipping_time integer,
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimension_set_id bigint,
    salesperson_code character varying(20),
    campaign_no character varying(20),
    opportunity_no character varying(20),
    your_reference character varying(35),
    external_document_no character varying(35),
    quote_no character varying(20),
    blanket_order_no character varying(20),
    correction boolean DEFAULT false NOT NULL,
    source_code character varying(10),
    reason_code character varying(10),
    user_id character varying(50),
    comment boolean DEFAULT false NOT NULL,
    no_printed integer DEFAULT 0 NOT NULL,
    on_hold character varying(3),
    prepayment_pct numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    customer_id uuid,
    bill_to_customer_id uuid,
    document_id character varying(50),
    applies_to_doc_type character varying(50),
    applies_to_doc_no character varying(20),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: sales_shipment_headers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_shipment_headers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_shipment_headers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_shipment_headers_id_seq OWNED BY public.sales_shipment_headers.id;


--
-- Name: sales_shipment_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales_shipment_lines (
    id bigint NOT NULL,
    sales_shipment_header_id bigint NOT NULL,
    document_no character varying(20) NOT NULL,
    line_no integer NOT NULL,
    type character varying(20) NOT NULL,
    no character varying(20),
    variant_code character varying(10),
    description character varying(100) NOT NULL,
    description_2 character varying(50),
    location_code character varying(10),
    bin_code character varying(20),
    posting_group character varying(20),
    quantity numeric(18,4) NOT NULL,
    quantity_base numeric(18,4) NOT NULL,
    qty_shipped_not_invoiced numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    quantity_invoiced numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_invoiced_base numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure character varying(50) NOT NULL,
    unit_of_measure_code character varying(10) NOT NULL,
    qty_per_unit_of_measure numeric(18,4) DEFAULT '1'::numeric NOT NULL,
    unit_price numeric(18,4) NOT NULL,
    unit_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    unit_cost_lcy numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    line_discount_pct numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    line_amount numeric(18,4) NOT NULL,
    amount numeric(18,4) NOT NULL,
    amount_including_vat numeric(18,4) NOT NULL,
    vat_base_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    vat_pct numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    allow_invoice_disc boolean DEFAULT true NOT NULL,
    allow_line_disc boolean DEFAULT true NOT NULL,
    order_no character varying(20),
    order_line_no integer,
    drop_shipment boolean DEFAULT false NOT NULL,
    purchase_order_no character varying(20),
    purch_order_line_no integer,
    special_order boolean DEFAULT false NOT NULL,
    special_order_purchase_no character varying(20),
    special_order_purch_line_no integer,
    blanket_order_no character varying(20),
    blanket_order_line_no integer,
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    dimension_set_id bigint,
    serial_no character varying(50),
    lot_no character varying(50),
    expiration_date date,
    appl_to_item_entry integer,
    item_shpt_entry_no integer,
    appl_from_item_entry integer,
    gen_bus_posting_group character varying(20),
    gen_prod_posting_group character varying(20),
    vat_prod_posting_group character varying(20),
    tax_group_code character varying(20),
    tax_liable boolean DEFAULT false NOT NULL,
    tax_area_code character varying(20),
    item_charge_base_amount numeric(18,4),
    allow_item_charge_assignment boolean DEFAULT true NOT NULL,
    qty_to_assign numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    qty_assigned numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    gross_weight numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    net_weight numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    units_per_parcel numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    unit_volume numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    shipment_date date NOT NULL,
    requested_delivery_date date,
    promised_delivery_date date,
    planned_delivery_date date,
    planned_shipment_date date,
    shipping_time integer,
    outbound_whse_handling_time integer,
    job_no character varying(20),
    job_task_no character varying(20),
    job_contract_entry_no integer,
    fa_posting_date date,
    depreciation_book_code character varying(10),
    depr_until_fa_posting_date boolean DEFAULT false NOT NULL,
    duplicate_in_depreciation_book character varying(10),
    use_duplication_list boolean DEFAULT false NOT NULL,
    item_reference_no character varying(50),
    item_reference_type character varying(30),
    item_reference_type_no character varying(30),
    item_reference_unit_of_measure character varying(10),
    ic_item_reference_no character varying(50),
    ic_partner_ref_type character varying(30),
    ic_partner_reference character varying(20),
    correction boolean DEFAULT false NOT NULL,
    return_reason_code character varying(10),
    attached_to_line_no integer,
    customer_price_group character varying(10),
    customer_disc_group character varying(20),
    work_type_code character varying(10),
    posting_date date NOT NULL,
    currency_code character varying(10),
    responsibility_center character varying(10),
    item_category_code character varying(20),
    nonstock boolean DEFAULT false NOT NULL,
    purchasing_code character varying(10),
    sales_order_line_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sales_shipment_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_shipment_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_shipment_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_shipment_lines_id_seq OWNED BY public.sales_shipment_lines.id;


--
-- Name: salesperson_purchasers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.salesperson_purchasers (
    code character varying(20) NOT NULL,
    name character varying(100) NOT NULL,
    commission_pct numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    phone_no character varying(30),
    email character varying(80),
    employee_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: shipment_methods; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.shipment_methods (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(100) NOT NULL,
    search_description character varying(100),
    incoterm_code character varying(10),
    is_incoterm boolean DEFAULT false NOT NULL,
    transport_mode character varying(20),
    seller_pays_insurance boolean DEFAULT false NOT NULL,
    seller_pays_freight boolean DEFAULT false NOT NULL,
    seller_pays_duty boolean DEFAULT false NOT NULL,
    default_shipping_agent_id bigint,
    default_service_code character varying(20),
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    is_active boolean DEFAULT true NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    notes text,
    extended_fields json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: shipment_methods_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.shipment_methods_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: shipment_methods_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.shipment_methods_id_seq OWNED BY public.shipment_methods.id;


--
-- Name: shipping_agent_services; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.shipping_agent_services (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: shipping_agent_services_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.shipping_agent_services_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: shipping_agent_services_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.shipping_agent_services_id_seq OWNED BY public.shipping_agent_services.id;


--
-- Name: shipping_agents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.shipping_agents (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(100) NOT NULL,
    search_name character varying(100),
    address character varying(100),
    address_2 character varying(50),
    city character varying(30),
    post_code character varying(20),
    country_code character varying(10),
    phone_no character varying(30),
    email character varying(80),
    website character varying(100),
    account_no character varying(50),
    api_key character varying(255),
    api_endpoint character varying(255),
    default_service_type character varying(30) DEFAULT 'ground'::character varying NOT NULL,
    default_insurance_amount numeric(18,4),
    requires_insurance boolean DEFAULT false NOT NULL,
    base_charge numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    fuel_surcharge_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    handling_charge numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    shortcut_dimension_1_code character varying(20),
    shortcut_dimension_2_code character varying(20),
    is_active boolean DEFAULT true NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    notes text,
    extended_fields json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: shipping_agents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.shipping_agents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: shipping_agents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.shipping_agents_id_seq OWNED BY public.shipping_agents.id;


--
-- Name: social_security_tiers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.social_security_tiers (
    id bigint NOT NULL,
    tier_code character varying(20) NOT NULL,
    code character varying(50),
    from_salary numeric(12,2) NOT NULL,
    to_salary numeric(12,2),
    employee_rate numeric(5,2) NOT NULL,
    employer_rate numeric(5,2) NOT NULL,
    max_base numeric(15,2),
    employee_max_amount numeric(15,2),
    employer_max_amount numeric(15,2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: social_security_tiers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.social_security_tiers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: social_security_tiers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.social_security_tiers_id_seq OWNED BY public.social_security_tiers.id;


--
-- Name: sync_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sync_logs (
    id bigint NOT NULL,
    entity character varying(50) NOT NULL,
    started_at timestamp(0) without time zone NOT NULL,
    completed_at timestamp(0) without time zone,
    total_records integer DEFAULT 0 NOT NULL,
    synced_records integer DEFAULT 0 NOT NULL,
    errors text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sync_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sync_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sync_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sync_logs_id_seq OWNED BY public.sync_logs.id;


--
-- Name: tax_brackets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tax_brackets (
    id bigint NOT NULL,
    tax_table_id bigint NOT NULL,
    from_amount numeric(15,2) NOT NULL,
    to_amount numeric(15,2),
    rate numeric(8,4) NOT NULL,
    base_tax numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: tax_brackets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tax_brackets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tax_brackets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tax_brackets_id_seq OWNED BY public.tax_brackets.id;


--
-- Name: tax_tables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tax_tables (
    id bigint NOT NULL,
    jurisdiction character varying(50) NOT NULL,
    effective_date date NOT NULL,
    name character varying(255),
    country_code character varying(10),
    state_code character varying(10),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: tax_tables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tax_tables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tax_tables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tax_tables_id_seq OWNED BY public.tax_tables.id;


--
-- Name: unit_of_measures; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.unit_of_measures (
    id bigint NOT NULL,
    uom_code character varying(10) NOT NULL,
    description character varying(50) NOT NULL,
    conversion_factor numeric(18,6) DEFAULT '1'::numeric NOT NULL,
    is_base_uom boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: unit_of_measures_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.unit_of_measures_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: unit_of_measures_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.unit_of_measures_id_seq OWNED BY public.unit_of_measures.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    salesperson_code character varying(20),
    employee_id bigint
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: value_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.value_entries (
    id bigint NOT NULL,
    entry_no bigint NOT NULL,
    item_ledger_entry_no bigint,
    item_ledger_entry_type bigint NOT NULL,
    source_type character varying(50),
    source_no character varying(50),
    source_line_no integer,
    source_batch_name character varying(50),
    item_no character varying(50) NOT NULL,
    variant_code character varying(50),
    location_code character varying(50) NOT NULL,
    bin_code character varying(50),
    posting_date date NOT NULL,
    valuation_date date,
    document_type character varying(50),
    document_no character varying(50),
    document_line_no integer,
    description character varying(255),
    quantity numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    invoiced_quantity numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    costing_method character varying(50),
    cost_amount_actual numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    cost_amount_actual_acy numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    cost_amount_expected numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    cost_amount_expected_acy numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    direct_cost_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    indirect_cost_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    overhead_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    variance_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    purchase_variance_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    material_variance_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    capacity_variance_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    capacity_overhead_variance_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    manufacturing_overhead_variance_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    unit_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    unit_cost_acy numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    single_level_material_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    single_level_capacity_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    single_level_subcontracted_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    single_level_overhead_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    single_level_mfg_ovhd_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    rollover_amount numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    capacity_type character varying(50),
    capacity_no character varying(50),
    routing_no integer,
    routing_reference_no integer,
    operation_no character varying(50),
    work_center_purch_capacity numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    work_center_purch_oh_capacity numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    work_center_purch_direct_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    work_center_purch_ovhd_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    production_order_no character varying(50),
    production_order_line_no character varying(50),
    production_order_component_line_no character varying(50),
    prod_order_line_item_no character varying(50),
    purchase_order_no character varying(50),
    purchase_order_line_no character varying(50),
    sales_order_no character varying(50),
    sales_order_line_no character varying(50),
    vendor_no character varying(50),
    customer_no character varying(50),
    serial_no character varying(50),
    lot_no character varying(50),
    expiration_date date,
    gl_posted boolean DEFAULT false NOT NULL,
    gl_posting_date date,
    gl_entry_no bigint,
    gl_account_no character varying(50),
    balancing_account_no character varying(50),
    cost_adjusted boolean DEFAULT false NOT NULL,
    cost_adjustment_date date,
    cost_adjustment_entry_no bigint,
    cost_is_adjusted boolean DEFAULT false NOT NULL,
    cost_is_changed_by_user boolean DEFAULT false NOT NULL,
    global_dimension_1_code character varying(50),
    global_dimension_2_code character varying(50),
    shortcut_dimension_codes json,
    dimension_set_id json,
    user_id character varying(50),
    source_code character varying(50),
    reason_code character varying(50),
    completely_invoiced boolean DEFAULT false NOT NULL,
    last_invoice boolean DEFAULT false NOT NULL,
    expected_cost boolean DEFAULT false NOT NULL,
    partial_posted boolean DEFAULT false NOT NULL,
    entry_type character varying(50),
    adjustment_entry_no bigint,
    original_entry_no bigint,
    original_document_no character varying(50),
    original_posting_date date,
    job_no character varying(50),
    job_task_no character varying(50),
    job_line_type character varying(50),
    warehouse_activity_no character varying(50),
    warehouse_line_no integer,
    registering_no character varying(50),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: value_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.value_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: value_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.value_entries_id_seq OWNED BY public.value_entries.id;


--
-- Name: vat_business_posting_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vat_business_posting_groups (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(255) NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: vat_business_posting_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vat_business_posting_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vat_business_posting_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vat_business_posting_groups_id_seq OWNED BY public.vat_business_posting_groups.id;


--
-- Name: vat_masters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vat_masters (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(100) NOT NULL,
    purchase_account_id bigint,
    sales_account_id bigint,
    percentage numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: vat_masters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vat_masters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vat_masters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vat_masters_id_seq OWNED BY public.vat_masters.id;


--
-- Name: vat_posting_setups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vat_posting_setups (
    id bigint NOT NULL,
    vat_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vat_calculation_type character varying(255) NOT NULL,
    vat_business_posting_group_id bigint NOT NULL,
    vat_product_posting_group_id bigint NOT NULL,
    sales_vat_account_id bigint,
    purchase_vat_account_id bigint,
    reverse_charge_vat_account_id bigint,
    vat_identifier character varying(20),
    blocked boolean DEFAULT false NOT NULL,
    eu_service boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT vat_posting_setups_vat_calculation_type_check CHECK (((vat_calculation_type)::text = ANY ((ARRAY['normal'::character varying, 'reverse_charge'::character varying, 'full_vat'::character varying, 'sales_tax'::character varying])::text[])))
);


--
-- Name: vat_posting_setups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vat_posting_setups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vat_posting_setups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vat_posting_setups_id_seq OWNED BY public.vat_posting_setups.id;


--
-- Name: vat_product_posting_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vat_product_posting_groups (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: vat_product_posting_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vat_product_posting_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vat_product_posting_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vat_product_posting_groups_id_seq OWNED BY public.vat_product_posting_groups.id;


--
-- Name: vendor_invoice_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vendor_invoice_lines (
    id bigint NOT NULL,
    vendor_invoice_id bigint NOT NULL,
    line_number integer NOT NULL,
    type character varying(255) DEFAULT 'ITEM'::character varying NOT NULL,
    item_id bigint,
    gl_account_id bigint,
    asset_id bigint,
    description text NOT NULL,
    description_2 character varying(255),
    quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure_code character varying(255),
    direct_unit_cost numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    line_discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    line_discount_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    line_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    tax_group_code character varying(255),
    tax_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    purchase_order_id bigint,
    purchase_order_line_no integer,
    purchase_receipt_id bigint,
    purchase_receipt_line_no integer,
    shortcut_dimension_1_code character varying(255),
    shortcut_dimension_2_code character varying(255),
    dimension_set_id bigint,
    capex_project_id bigint,
    capex_project_line_id bigint,
    production_order_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: vendor_invoice_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vendor_invoice_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendor_invoice_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendor_invoice_lines_id_seq OWNED BY public.vendor_invoice_lines.id;


--
-- Name: vendor_invoices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vendor_invoices (
    id bigint NOT NULL,
    document_number character varying(255) NOT NULL,
    external_document_no character varying(255),
    vendor_id bigint NOT NULL,
    vendor_invoice_no character varying(255) NOT NULL,
    vendor_invoice_date date NOT NULL,
    document_type character varying(255) DEFAULT 'INVOICE'::character varying NOT NULL,
    status character varying(255) DEFAULT 'OPEN'::character varying NOT NULL,
    amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    amount_including_tax numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    currency_code character varying(255) DEFAULT 'USD'::character varying NOT NULL,
    exchange_rate numeric(15,6) DEFAULT '1'::numeric NOT NULL,
    amount_lcy numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    posting_date date NOT NULL,
    due_date date NOT NULL,
    receipt_date date,
    payment_terms_code character varying(255),
    payment_method_code character varying(255),
    payable_gl_account_id bigint NOT NULL,
    expense_gl_account_id bigint,
    source_document_type character varying(255),
    source_document_id bigint,
    source_document_no character varying(255),
    shortcut_dimension_1_code character varying(255),
    shortcut_dimension_2_code character varying(255),
    dimension_set_id bigint,
    requested_by bigint,
    approved_by bigint,
    approved_at timestamp(0) without time zone,
    posted boolean DEFAULT false NOT NULL,
    posted_at timestamp(0) without time zone,
    posted_by bigint,
    remaining_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    last_payment_date date,
    capex_project_id bigint,
    capitalized boolean DEFAULT false NOT NULL,
    description text,
    internal_notes text,
    created_by bigint NOT NULL,
    last_modified_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: vendor_invoices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vendor_invoices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendor_invoices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendor_invoices_id_seq OWNED BY public.vendor_invoices.id;


--
-- Name: vendor_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vendor_items (
    id bigint NOT NULL,
    vendor_id bigint NOT NULL,
    item_id bigint NOT NULL,
    vendor_item_number character varying(50) NOT NULL,
    vendor_item_name character varying(255),
    vendor_item_category character varying(50),
    unit_cost numeric(15,4) NOT NULL,
    purchase_uom_id bigint,
    currency_id bigint,
    price_breaks json,
    minimum_order_qty numeric(15,4) DEFAULT '1'::numeric NOT NULL,
    lead_time_days integer DEFAULT 0 NOT NULL,
    is_preferred boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    effective_date date,
    expiry_date date,
    last_purchase_date date,
    last_purchase_price numeric(15,4),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: vendor_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vendor_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendor_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendor_items_id_seq OWNED BY public.vendor_items.id;


--
-- Name: vendor_ledger_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vendor_ledger_entries (
    id bigint NOT NULL,
    entry_number bigint NOT NULL,
    vendor_id bigint NOT NULL,
    document_type character varying(255) NOT NULL,
    document_number character varying(20) NOT NULL,
    external_document_number character varying(50),
    description character varying(255) NOT NULL,
    comment text,
    posting_date date NOT NULL,
    document_date date NOT NULL,
    due_date date,
    debit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    credit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    amount numeric(15,4) NOT NULL,
    running_balance numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    remaining_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    open boolean DEFAULT true NOT NULL,
    applied_to_entries json,
    fully_applied boolean DEFAULT false NOT NULL,
    currency_code character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    original_debit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    original_credit_amount numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    currency_factor numeric(15,6) DEFAULT '1'::numeric NOT NULL,
    general_business_posting_group_id bigint,
    vendor_posting_group_id bigint,
    gl_entry_id bigint,
    source_id bigint,
    source_type character varying(50),
    created_by bigint NOT NULL,
    reversed boolean DEFAULT false NOT NULL,
    reversed_at timestamp(0) without time zone,
    reversed_by bigint,
    reversal_entry_number character varying(20),
    payment_terms_code character varying(20),
    payment_discount_percent numeric(5,2),
    payment_discount_due_date date,
    retainage_amount numeric(15,4),
    retainage_due_date date,
    dimensions json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    currency_id bigint,
    CONSTRAINT vendor_ledger_entries_document_type_check CHECK (((document_type)::text = ANY ((ARRAY['PURCHASE_INVOICE'::character varying, 'PURCHASE_CREDIT_MEMO'::character varying, 'PAYMENT'::character varying, 'REFUND'::character varying, 'CREDIT_MEMO_APPLICATION'::character varying, 'FINANCE_CHARGE'::character varying, 'ADJUSTMENT'::character varying, 'WRITE_OFF'::character varying, 'PAYMENT_DISCOUNT'::character varying, 'BANK_TRANSFER'::character varying])::text[])))
);


--
-- Name: vendor_ledger_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vendor_ledger_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendor_ledger_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendor_ledger_entries_id_seq OWNED BY public.vendor_ledger_entries.id;


--
-- Name: vendor_posting_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vendor_posting_groups (
    id bigint NOT NULL,
    code character varying(20) NOT NULL,
    description character varying(255) NOT NULL,
    payables_account character varying(20),
    service_charge_acc character varying(20),
    payment_disc_debit_acc character varying(20),
    payment_disc_credit_acc character varying(20),
    invoice_rounding_account character varying(20),
    debit_curr_appl_acc character varying(20),
    credit_curr_appl_acc character varying(20),
    debit_appl_acc character varying(20),
    credit_appl_acc character varying(20),
    prepayment_account character varying(20),
    blocked boolean DEFAULT false NOT NULL,
    payables_account_id bigint,
    payment_disc_debit_account_id bigint,
    payment_disc_credit_account_id bigint,
    invoice_rounding_account_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: vendor_posting_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vendor_posting_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendor_posting_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendor_posting_groups_id_seq OWNED BY public.vendor_posting_groups.id;


--
-- Name: vendors; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vendors (
    id bigint NOT NULL,
    vendor_code character varying(20) NOT NULL,
    vendor_name character varying(255) NOT NULL,
    address text,
    email character varying(255),
    phone character varying(255),
    mobile character varying(50),
    contact_person character varying(100),
    city character varying(100),
    state character varying(100),
    postal_code character varying(20),
    country character varying(100),
    tax_id character varying(50),
    currency character(3) DEFAULT 'NGN'::bpchar NOT NULL,
    lead_time_days integer,
    minimum_order_amount numeric(15,4),
    is_active boolean DEFAULT true NOT NULL,
    notes text,
    gen_bus_posting_group character varying(20),
    vendor_posting_group character varying(20),
    vat_bus_posting_group character varying(20),
    general_business_posting_group_id bigint NOT NULL,
    vendor_posting_group_id bigint NOT NULL,
    vat_business_posting_group_id bigint,
    contact_id bigint NOT NULL,
    payment_terms_code character varying(20),
    payment_terms character varying(255),
    blocked boolean DEFAULT false NOT NULL,
    blocked_reason character varying(255) DEFAULT 'NONE'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_price_inclusive boolean DEFAULT false NOT NULL,
    CONSTRAINT vendors_blocked_reason_check CHECK (((blocked_reason)::text = ANY ((ARRAY['NONE'::character varying, 'PAYMENT'::character varying, 'INVOICE'::character varying, 'INACTIVE'::character varying, 'ALL'::character varying])::text[])))
);


--
-- Name: vendors_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vendors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendors_id_seq OWNED BY public.vendors.id;


--
-- Name: warehouse_activities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_activities (
    id bigint NOT NULL,
    no character varying(50) NOT NULL,
    activity_type character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    location_id bigint NOT NULL,
    zone_id bigint,
    bin_id bigint,
    source_document character varying(50),
    source_no character varying(50),
    source_line_no integer,
    source_id bigint,
    assigned_user_id bigint,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    remarks text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT warehouse_activities_activity_type_check CHECK (((activity_type)::text = ANY ((ARRAY['put_away'::character varying, 'pick'::character varying, 'movement'::character varying, 'inventory'::character varying, 'receipt'::character varying, 'shipment'::character varying, 'internal_pick'::character varying, 'internal_put_away'::character varying])::text[]))),
    CONSTRAINT warehouse_activities_status_check CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'released'::character varying, 'in_progress'::character varying, 'completed'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: warehouse_activities_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_activities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_activities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_activities_id_seq OWNED BY public.warehouse_activities.id;


--
-- Name: warehouse_activity_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_activity_lines (
    id bigint NOT NULL,
    warehouse_activity_id bigint NOT NULL,
    line_no integer NOT NULL,
    item_id bigint NOT NULL,
    quantity_to_handle numeric(15,4) NOT NULL,
    quantity_handled numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    quantity_base numeric(15,4) NOT NULL,
    unit_of_measure_code character varying(20) NOT NULL,
    source_zone_id bigint,
    source_bin_id bigint,
    source_lot_no character varying(50),
    source_serial_no character varying(50),
    destination_zone_id bigint,
    destination_bin_id bigint,
    destination_lot_no character varying(50),
    destination_serial_no character varying(50),
    breakbulk boolean DEFAULT false NOT NULL,
    breakbulk_quantity numeric(15,4),
    lot_no character varying(50),
    serial_no character varying(50),
    expiration_date date,
    warranty_date date,
    line_status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    handled_by bigint,
    handled_at timestamp(0) without time zone,
    remarks text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT warehouse_activity_lines_line_status_check CHECK (((line_status)::text = ANY ((ARRAY['open'::character varying, 'in_progress'::character varying, 'completed'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: warehouse_activity_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_activity_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_activity_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_activity_lines_id_seq OWNED BY public.warehouse_activity_lines.id;


--
-- Name: warehouse_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_entries (
    id bigint NOT NULL,
    item_id bigint NOT NULL,
    location_id bigint NOT NULL,
    zone_id bigint,
    bin_id bigint,
    lot_no character varying(50),
    serial_no character varying(50),
    expiration_date date,
    entry_type character varying(255) NOT NULL,
    quantity numeric(15,4) NOT NULL,
    quantity_base numeric(15,4) NOT NULL,
    unit_of_measure_code character varying(20) NOT NULL,
    unit_cost numeric(15,4),
    total_cost numeric(15,4),
    document_type character varying(50),
    document_no character varying(50),
    document_line_no integer,
    warehouse_activity_line_id bigint,
    item_ledger_entry_id bigint,
    entry_timestamp timestamp(0) without time zone NOT NULL,
    created_by bigint NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT warehouse_entries_entry_type_check CHECK (((entry_type)::text = ANY ((ARRAY['positive'::character varying, 'negative'::character varying, 'transfer'::character varying])::text[])))
);


--
-- Name: warehouse_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_entries_id_seq OWNED BY public.warehouse_entries.id;


--
-- Name: warehouse_journal_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_journal_batches (
    id bigint NOT NULL,
    template_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    assigned_user_id bigint,
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    location_id bigint NOT NULL,
    zone_id bigint,
    journal_type character varying(255),
    dimension_filter json,
    reason_code character varying(20),
    copy_from_warehouse_activity boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT warehouse_journal_batches_journal_type_check CHECK (((journal_type)::text = ANY ((ARRAY['pick'::character varying, 'put_away'::character varying, 'movement'::character varying, 'physical_inventory'::character varying, 'adjustment'::character varying])::text[]))),
    CONSTRAINT warehouse_journal_batches_status_check CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'released'::character varying, 'posted'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: warehouse_journal_batches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_journal_batches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_journal_batches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_journal_batches_id_seq OWNED BY public.warehouse_journal_batches.id;


--
-- Name: warehouse_journal_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_journal_lines (
    id bigint NOT NULL,
    batch_id bigint NOT NULL,
    line_no integer DEFAULT 10000 NOT NULL,
    posting_date date NOT NULL,
    entry_type character varying(255) NOT NULL,
    document_no character varying(50),
    warehouse_activity_id bigint,
    warehouse_activity_line_id bigint,
    item_id bigint NOT NULL,
    item_no character varying(50) NOT NULL,
    description character varying(100),
    unit_of_measure_code character varying(20) NOT NULL,
    quantity numeric(15,4) NOT NULL,
    quantity_base numeric(15,4) NOT NULL,
    qty_calculated numeric(15,4),
    qty_physical numeric(15,4),
    source_location_id bigint NOT NULL,
    source_zone_id bigint,
    source_bin_id bigint,
    source_lot_no character varying(50),
    source_serial_no character varying(50),
    destination_location_id bigint,
    destination_zone_id bigint,
    destination_bin_id bigint,
    destination_lot_no character varying(50),
    destination_serial_no character varying(50),
    zone_id bigint,
    bin_id bigint,
    lot_no character varying(50),
    serial_no character varying(50),
    expiration_date date,
    reason_code character varying(20),
    comment text,
    phys_inventory boolean DEFAULT false NOT NULL,
    shortcut_dimension_1_code character varying(50),
    shortcut_dimension_2_code character varying(50),
    dimension_set_entry json,
    source_code character varying(20),
    created_by bigint NOT NULL,
    created_at timestamp(0) without time zone NOT NULL,
    line_status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    warehouse_entry_id bigint,
    CONSTRAINT warehouse_journal_lines_entry_type_check CHECK (((entry_type)::text = ANY ((ARRAY['pick'::character varying, 'put_away'::character varying, 'movement'::character varying, 'positive_adj'::character varying, 'negative_adj'::character varying, 'physical_inventory'::character varying])::text[]))),
    CONSTRAINT warehouse_journal_lines_line_status_check CHECK (((line_status)::text = ANY ((ARRAY['open'::character varying, 'checked'::character varying, 'rejected'::character varying, 'posted'::character varying])::text[])))
);


--
-- Name: warehouse_journal_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_journal_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_journal_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_journal_lines_id_seq OWNED BY public.warehouse_journal_lines.id;


--
-- Name: warehouse_journal_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_journal_templates (
    id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(100),
    journal_type character varying(255) DEFAULT 'movement'::character varying NOT NULL,
    number_series_id bigint NOT NULL,
    source_code character varying(20),
    zone_mandatory boolean DEFAULT false NOT NULL,
    bin_mandatory boolean DEFAULT true NOT NULL,
    item_tracking_mandatory boolean DEFAULT false NOT NULL,
    directed_put_away_and_pick boolean DEFAULT false NOT NULL,
    require_warehouse_activity boolean DEFAULT false NOT NULL,
    is_physical_inventory boolean DEFAULT false NOT NULL,
    calculate_inventory boolean DEFAULT false NOT NULL,
    items_not_on_inventory boolean DEFAULT false NOT NULL,
    require_reason_code boolean DEFAULT true NOT NULL,
    allowed_reason_codes json,
    default_adjustment_account_id bigint,
    mandatory_dimensions json,
    default_dimensions json,
    test_report_before_posting boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT warehouse_journal_templates_journal_type_check CHECK (((journal_type)::text = ANY ((ARRAY['pick'::character varying, 'put_away'::character varying, 'movement'::character varying, 'physical_inventory'::character varying, 'adjustment'::character varying])::text[])))
);


--
-- Name: warehouse_journal_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_journal_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_journal_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_journal_templates_id_seq OWNED BY public.warehouse_journal_templates.id;


--
-- Name: warehouse_pick_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_pick_lines (
    id bigint NOT NULL,
    warehouse_pick_id bigint NOT NULL,
    line_no integer NOT NULL,
    source_line_no integer,
    item_id bigint NOT NULL,
    description character varying(200),
    quantity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    quantity_to_handle numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    quantity_handled numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    quantity_base numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure_code character varying(20) DEFAULT 'PCS'::character varying NOT NULL,
    zone_id bigint,
    bin_id bigint,
    lot_no character varying(50),
    serial_no character varying(50),
    expiration_date date,
    destination_zone_id bigint,
    destination_bin_id bigint,
    line_status character varying(30) DEFAULT 'open'::character varying NOT NULL,
    handled_by bigint,
    handled_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: warehouse_pick_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_pick_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_pick_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_pick_lines_id_seq OWNED BY public.warehouse_pick_lines.id;


--
-- Name: warehouse_picks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_picks (
    id bigint NOT NULL,
    no character varying(50) NOT NULL,
    status character varying(30) DEFAULT 'open'::character varying NOT NULL,
    location_id bigint NOT NULL,
    assigned_user_id bigint,
    source_document character varying(50),
    source_no character varying(50),
    source_id bigint,
    warehouse_shipment_id bigint,
    due_date date,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    created_by bigint,
    remarks text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: warehouse_picks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_picks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_picks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_picks_id_seq OWNED BY public.warehouse_picks.id;


--
-- Name: warehouse_putaway_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_putaway_lines (
    id bigint NOT NULL,
    warehouse_putaway_id bigint NOT NULL,
    line_no integer NOT NULL,
    action_type character varying(255) NOT NULL,
    item_id bigint NOT NULL,
    bin_id bigint NOT NULL,
    zone_id bigint,
    quantity numeric(15,4) NOT NULL,
    qty_to_handle numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    qty_handled numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure character varying(20) NOT NULL,
    source_document character varying(255) NOT NULL,
    source_no character varying(50) NOT NULL,
    source_line_no integer NOT NULL,
    breakbulk boolean DEFAULT false NOT NULL,
    item_tracking text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT warehouse_putaway_lines_action_type_check CHECK (((action_type)::text = ANY ((ARRAY['Take'::character varying, 'Place'::character varying])::text[]))),
    CONSTRAINT warehouse_putaway_lines_source_document_check CHECK (((source_document)::text = ANY ((ARRAY['Purchase Order'::character varying, 'Sales Return'::character varying, 'Inbound Transfer'::character varying, 'Internal Put-away'::character varying])::text[])))
);


--
-- Name: warehouse_putaway_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_putaway_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_putaway_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_putaway_lines_id_seq OWNED BY public.warehouse_putaway_lines.id;


--
-- Name: warehouse_putaways; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_putaways (
    id bigint NOT NULL,
    no character varying(50) NOT NULL,
    location_id bigint NOT NULL,
    warehouse_receipt_id bigint,
    assigned_user_id bigint,
    status character varying(255) DEFAULT 'Open'::character varying NOT NULL,
    sorting_method character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT warehouse_putaways_sorting_method_check CHECK (((sorting_method)::text = ANY ((ARRAY['Item'::character varying, 'Bin Ranking'::character varying, 'Document'::character varying, 'Due Date'::character varying])::text[]))),
    CONSTRAINT warehouse_putaways_status_check CHECK (((status)::text = ANY ((ARRAY['Open'::character varying, 'In Progress'::character varying, 'Completed'::character varying])::text[])))
);


--
-- Name: warehouse_putaways_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_putaways_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_putaways_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_putaways_id_seq OWNED BY public.warehouse_putaways.id;


--
-- Name: warehouse_receipt_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_receipt_lines (
    id bigint NOT NULL,
    warehouse_receipt_id bigint NOT NULL,
    line_number integer NOT NULL,
    item_id bigint NOT NULL,
    variant_code character varying(20),
    description character varying(255),
    quantity numeric(15,4) NOT NULL,
    quantity_received numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    quantity_outstanding numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure_code character varying(20) NOT NULL,
    qty_per_unit_of_measure numeric(10,4) DEFAULT '1'::numeric NOT NULL,
    zone_code character varying(20),
    bin_code character varying(20),
    serial_number character varying(50),
    lot_number character varying(50),
    expiration_date date,
    source_line_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: warehouse_receipt_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_receipt_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_receipt_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_receipt_lines_id_seq OWNED BY public.warehouse_receipt_lines.id;


--
-- Name: warehouse_receipts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_receipts (
    id bigint NOT NULL,
    document_number character varying(20) NOT NULL,
    location_id bigint NOT NULL,
    source_document character varying(255) NOT NULL,
    source_document_id bigint NOT NULL,
    source_document_number character varying(20) NOT NULL,
    vendor_id bigint,
    status character varying(255) DEFAULT 'OPEN'::character varying NOT NULL,
    assigned_user_id bigint,
    receipt_date date NOT NULL,
    expected_receipt_date date,
    posted_date timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT warehouse_receipts_source_document_check CHECK (((source_document)::text = ANY ((ARRAY['PURCHASE_ORDER'::character varying, 'TRANSFER_ORDER'::character varying, 'RETURN_ORDER'::character varying, 'SALES_RETURN'::character varying])::text[]))),
    CONSTRAINT warehouse_receipts_status_check CHECK (((status)::text = ANY ((ARRAY['OPEN'::character varying, 'RELEASED'::character varying, 'PARTIALLY_RECEIVED'::character varying, 'RECEIVED'::character varying])::text[])))
);


--
-- Name: warehouse_receipts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_receipts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_receipts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_receipts_id_seq OWNED BY public.warehouse_receipts.id;


--
-- Name: warehouse_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_requests (
    id bigint NOT NULL,
    source_document character varying(50) NOT NULL,
    source_no character varying(50) NOT NULL,
    source_line_no integer NOT NULL,
    source_id bigint,
    request_type character varying(255) NOT NULL,
    location_id bigint NOT NULL,
    zone_id bigint,
    bin_id bigint,
    item_id bigint NOT NULL,
    quantity numeric(15,4) NOT NULL,
    quantity_base numeric(15,4) NOT NULL,
    unit_of_measure_code character varying(20) NOT NULL,
    quantity_outstanding numeric(15,4) NOT NULL,
    lot_no character varying(50),
    serial_no character varying(50),
    expiration_date date,
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    warehouse_activity_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT warehouse_requests_request_type_check CHECK (((request_type)::text = ANY ((ARRAY['pick'::character varying, 'put_away'::character varying, 'movement'::character varying])::text[]))),
    CONSTRAINT warehouse_requests_status_check CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'partial'::character varying, 'completed'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: warehouse_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_requests_id_seq OWNED BY public.warehouse_requests.id;


--
-- Name: warehouse_setup; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_setup (
    id bigint NOT NULL,
    location_mandatory boolean DEFAULT false NOT NULL,
    bin_mandatory boolean DEFAULT false NOT NULL,
    require_pick boolean DEFAULT false NOT NULL,
    require_putaway boolean DEFAULT false NOT NULL,
    require_receive boolean DEFAULT false NOT NULL,
    require_shipment boolean DEFAULT false NOT NULL,
    directed_putaway_and_pick boolean DEFAULT false NOT NULL,
    warehouse_receipt_nos character varying(255),
    warehouse_shipment_nos character varying(255),
    internal_putaway_nos character varying(255),
    internal_pick_nos character varying(255),
    bin_capacity_policy character varying(255) DEFAULT 'Never Check'::character varying NOT NULL,
    allow_breakbulk boolean DEFAULT false NOT NULL,
    putaway_template_nos character varying(255),
    pick_according_to_fefo boolean DEFAULT false NOT NULL,
    default_bin_selection character varying(255) DEFAULT 'Fixed Bin'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT warehouse_setup_bin_capacity_policy_check CHECK (((bin_capacity_policy)::text = ANY ((ARRAY['Never Check'::character varying, 'Check'::character varying, 'Prohibit'::character varying])::text[]))),
    CONSTRAINT warehouse_setup_default_bin_selection_check CHECK (((default_bin_selection)::text = ANY ((ARRAY['Fixed Bin'::character varying, 'Last Bin Used'::character varying, 'WMS Default'::character varying])::text[])))
);


--
-- Name: warehouse_setup_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_setup_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_setup_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_setup_id_seq OWNED BY public.warehouse_setup.id;


--
-- Name: warehouse_shipment_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_shipment_lines (
    id bigint NOT NULL,
    warehouse_shipment_id bigint NOT NULL,
    line_number integer NOT NULL,
    item_id bigint NOT NULL,
    variant_code character varying(20),
    description character varying(255),
    quantity numeric(15,4) NOT NULL,
    quantity_shipped numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    quantity_outstanding numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    quantity_picked numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    unit_of_measure_code character varying(20) NOT NULL,
    qty_per_unit_of_measure numeric(10,4) DEFAULT '1'::numeric NOT NULL,
    zone_code character varying(20),
    bin_code character varying(20),
    serial_number character varying(50),
    lot_number character varying(50),
    source_line_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: warehouse_shipment_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_shipment_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_shipment_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_shipment_lines_id_seq OWNED BY public.warehouse_shipment_lines.id;


--
-- Name: warehouse_shipments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouse_shipments (
    id bigint NOT NULL,
    document_number character varying(20) NOT NULL,
    location_id bigint NOT NULL,
    source_document character varying(255) NOT NULL,
    source_document_id bigint NOT NULL,
    source_document_number character varying(20) NOT NULL,
    customer_id bigint,
    shipping_agent_code character varying(20),
    shipping_agent_service_code character varying(20),
    external_document_number character varying(50),
    status character varying(255) DEFAULT 'OPEN'::character varying NOT NULL,
    assigned_user_id bigint,
    shipment_date date NOT NULL,
    planned_delivery_date date,
    posted_date timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT warehouse_shipments_source_document_check CHECK (((source_document)::text = ANY ((ARRAY['PURCHASE_ORDER'::character varying, 'TRANSFER_ORDER'::character varying, 'RETURN_ORDER'::character varying, 'SALES_RETURN'::character varying])::text[]))),
    CONSTRAINT warehouse_shipments_status_check CHECK (((status)::text = ANY ((ARRAY['OPEN'::character varying, 'RELEASED'::character varying, 'PARTIALLY_SHIPPED'::character varying, 'SHIPPED'::character varying, 'INVOICED'::character varying, 'PARTIALLY_INVOICED'::character varying, 'INVOICED_AND_SHIPPED'::character varying])::text[])))
);


--
-- Name: warehouse_shipments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warehouse_shipments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warehouse_shipments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warehouse_shipments_id_seq OWNED BY public.warehouse_shipments.id;


--
-- Name: work_center_bins; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.work_center_bins (
    id bigint NOT NULL,
    work_center_id bigint NOT NULL,
    open_shop_floor_bin_id bigint,
    to_production_bin_id bigint,
    from_production_bin_id bigint,
    fixed_bin_id bigint,
    flushing_method character varying(255) DEFAULT 'manual'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT work_center_bins_flushing_method_check CHECK (((flushing_method)::text = ANY ((ARRAY['manual'::character varying, 'pick'::character varying, 'forward'::character varying, 'backward'::character varying, 'consume'::character varying])::text[])))
);


--
-- Name: work_center_bins_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.work_center_bins_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: work_center_bins_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.work_center_bins_id_seq OWNED BY public.work_center_bins.id;


--
-- Name: work_center_calendars; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.work_center_calendars (
    id bigint NOT NULL,
    work_center_id bigint NOT NULL,
    date date NOT NULL,
    is_working_day boolean DEFAULT true NOT NULL,
    start_time time(0) without time zone,
    end_time time(0) without time zone,
    capacity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    efficiency numeric(5,2) DEFAULT '100'::numeric NOT NULL,
    absence_code character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: work_center_calendars_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.work_center_calendars_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: work_center_calendars_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.work_center_calendars_id_seq OWNED BY public.work_center_calendars.id;


--
-- Name: work_center_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.work_center_groups (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: work_center_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.work_center_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: work_center_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.work_center_groups_id_seq OWNED BY public.work_center_groups.id;


--
-- Name: work_centers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.work_centers (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    work_center_group_id bigint,
    unit_of_measure_code character varying(255) DEFAULT 'MINUTES'::character varying NOT NULL,
    capacity numeric(15,4) DEFAULT '0'::numeric NOT NULL,
    efficiency numeric(5,2) DEFAULT '100'::numeric NOT NULL,
    maximum_efficiency numeric(5,2) DEFAULT '100'::numeric NOT NULL,
    minimum_efficiency numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    direct_unit_cost numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    indirect_cost_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    overhead_rate numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    queue_time numeric(18,4) DEFAULT '0'::numeric NOT NULL,
    queue_time_unit character varying(255) DEFAULT 'MINUTES'::character varying NOT NULL,
    location_code character varying(255),
    work_center_account_no character varying(255),
    subcontractor_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    work_center_gl_account_id bigint,
    fixed_asset_id bigint,
    operator_employee_id bigint
);


--
-- Name: work_centers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.work_centers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: work_centers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.work_centers_id_seq OWNED BY public.work_centers.id;


--
-- Name: zones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.zones (
    id bigint NOT NULL,
    location_id bigint NOT NULL,
    zone_code character varying(20) NOT NULL,
    zone_name character varying(100) NOT NULL,
    description character varying(255),
    zone_type character varying(255) DEFAULT 'STORAGE'::character varying NOT NULL,
    warehouse_class character varying(255) DEFAULT 'standard'::character varying NOT NULL,
    bin_type_code character varying(20),
    bin_mandatory boolean DEFAULT false NOT NULL,
    max_weight numeric(15,4),
    blocked boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT zones_warehouse_class_check CHECK (((warehouse_class)::text = ANY ((ARRAY['standard'::character varying, 'refrigerated'::character varying, 'frozen'::character varying, 'hazardous'::character varying, 'high_value'::character varying, 'quarantine'::character varying])::text[]))),
    CONSTRAINT zones_zone_type_check CHECK (((zone_type)::text = ANY ((ARRAY['RECEIVING'::character varying, 'STORAGE'::character varying, 'PICKING'::character varying, 'SHIPPING'::character varying, 'QUALITY_CONTROL'::character varying, 'CROSS_DOCK'::character varying])::text[])))
);


--
-- Name: zones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.zones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: zones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.zones_id_seq OWNED BY public.zones.id;


--
-- Name: account_schedule_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_schedule_lines ALTER COLUMN id SET DEFAULT nextval('public.account_schedule_lines_id_seq'::regclass);


--
-- Name: account_schedules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_schedules ALTER COLUMN id SET DEFAULT nextval('public.account_schedules_id_seq'::regclass);


--
-- Name: accounting_periods id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accounting_periods ALTER COLUMN id SET DEFAULT nextval('public.accounting_periods_id_seq'::regclass);


--
-- Name: actual_overhead_costs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actual_overhead_costs ALTER COLUMN id SET DEFAULT nextval('public.actual_overhead_costs_id_seq'::regclass);


--
-- Name: allocation_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocation_lines ALTER COLUMN id SET DEFAULT nextval('public.allocation_lines_id_seq'::regclass);


--
-- Name: allocations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocations ALTER COLUMN id SET DEFAULT nextval('public.allocations_id_seq'::regclass);


--
-- Name: approval_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_entries ALTER COLUMN id SET DEFAULT nextval('public.approval_entries_id_seq'::regclass);


--
-- Name: approval_template_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_template_entries ALTER COLUMN id SET DEFAULT nextval('public.approval_template_entries_id_seq'::regclass);


--
-- Name: approval_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_templates ALTER COLUMN id SET DEFAULT nextval('public.approval_templates_id_seq'::regclass);


--
-- Name: asset_components id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_components ALTER COLUMN id SET DEFAULT nextval('public.asset_components_id_seq'::regclass);


--
-- Name: asset_depreciation_ledger id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_depreciation_ledger ALTER COLUMN id SET DEFAULT nextval('public.fixed_asset_depreciation_ledger_id_seq'::regclass);


--
-- Name: asset_ledger_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_ledger_entries ALTER COLUMN id SET DEFAULT nextval('public.asset_ledger_entries_id_seq'::regclass);


--
-- Name: asset_maintenances id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_maintenances ALTER COLUMN id SET DEFAULT nextval('public.asset_maintenances_id_seq'::regclass);


--
-- Name: assets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets ALTER COLUMN id SET DEFAULT nextval('public.assets_id_seq'::regclass);


--
-- Name: attendance_ledger_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_ledger_entries ALTER COLUMN id SET DEFAULT nextval('public.attendance_ledger_entries_id_seq'::regclass);


--
-- Name: bank_account_ledger_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_ledger_entries ALTER COLUMN id SET DEFAULT nextval('public.bank_account_ledger_entries_id_seq'::regclass);


--
-- Name: bank_account_statement_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_statement_lines ALTER COLUMN id SET DEFAULT nextval('public.bank_account_statement_lines_id_seq'::regclass);


--
-- Name: bank_accounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_accounts ALTER COLUMN id SET DEFAULT nextval('public.bank_accounts_id_seq'::regclass);


--
-- Name: bank_reconciliations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_reconciliations ALTER COLUMN id SET DEFAULT nextval('public.bank_reconciliations_id_seq'::regclass);


--
-- Name: bin_contents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bin_contents ALTER COLUMN id SET DEFAULT nextval('public.bin_contents_id_seq'::regclass);


--
-- Name: bins id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bins ALTER COLUMN id SET DEFAULT nextval('public.bins_id_seq'::regclass);


--
-- Name: blanket_order_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_order_lines ALTER COLUMN id SET DEFAULT nextval('public.blanket_order_lines_id_seq'::regclass);


--
-- Name: blanket_orders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_orders ALTER COLUMN id SET DEFAULT nextval('public.blanket_orders_id_seq'::regclass);


--
-- Name: business_units id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.business_units ALTER COLUMN id SET DEFAULT nextval('public.business_units_id_seq'::regclass);


--
-- Name: businesses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.businesses ALTER COLUMN id SET DEFAULT nextval('public.businesses_id_seq'::regclass);


--
-- Name: campaign_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaign_items ALTER COLUMN id SET DEFAULT nextval('public.campaign_items_id_seq'::regclass);


--
-- Name: campaigns id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaigns ALTER COLUMN id SET DEFAULT nextval('public.campaigns_id_seq'::regclass);


--
-- Name: capacity_ledger_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capacity_ledger_entries ALTER COLUMN id SET DEFAULT nextval('public.capacity_ledger_entries_id_seq'::regclass);


--
-- Name: capex_project_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_project_lines ALTER COLUMN id SET DEFAULT nextval('public.capex_project_lines_id_seq'::regclass);


--
-- Name: capex_projects id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_projects ALTER COLUMN id SET DEFAULT nextval('public.capex_projects_id_seq'::regclass);


--
-- Name: cash_receipt_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cash_receipt_lines ALTER COLUMN id SET DEFAULT nextval('public.cash_receipt_lines_id_seq'::regclass);


--
-- Name: categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass);


--
-- Name: chart_of_accounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chart_of_accounts ALTER COLUMN id SET DEFAULT nextval('public.chart_of_accounts_id_seq'::regclass);


--
-- Name: company_information id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_information ALTER COLUMN id SET DEFAULT nextval('public.company_information_id_seq'::regclass);


--
-- Name: contacts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contacts ALTER COLUMN id SET DEFAULT nextval('public.contacts_id_seq'::regclass);


--
-- Name: currencies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies ALTER COLUMN id SET DEFAULT nextval('public.currencies_id_seq'::regclass);


--
-- Name: currency_adjustment_ledger id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_adjustment_ledger ALTER COLUMN id SET DEFAULT nextval('public.currency_adjustment_ledger_id_seq'::regclass);


--
-- Name: currency_buffers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_buffers ALTER COLUMN id SET DEFAULT nextval('public.currency_buffers_id_seq'::regclass);


--
-- Name: currency_exchange_rates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_exchange_rates ALTER COLUMN id SET DEFAULT nextval('public.currency_exchange_rates_id_seq'::regclass);


--
-- Name: customer_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_groups ALTER COLUMN id SET DEFAULT nextval('public.customer_groups_id_seq'::regclass);


--
-- Name: customer_ledger_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_ledger_entries ALTER COLUMN id SET DEFAULT nextval('public.customer_ledger_entries_id_seq'::regclass);


--
-- Name: customer_posting_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_posting_groups ALTER COLUMN id SET DEFAULT nextval('public.customer_posting_groups_id_seq'::regclass);


--
-- Name: customer_price_overrides id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_price_overrides ALTER COLUMN id SET DEFAULT nextval('public.customer_price_overrides_id_seq'::regclass);


--
-- Name: customers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers ALTER COLUMN id SET DEFAULT nextval('public.customers_id_seq'::regclass);


--
-- Name: default_dimensions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.default_dimensions ALTER COLUMN id SET DEFAULT nextval('public.default_dimensions_id_seq'::regclass);


--
-- Name: department_employee id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.department_employee ALTER COLUMN id SET DEFAULT nextval('public.department_employee_id_seq'::regclass);


--
-- Name: departments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments ALTER COLUMN id SET DEFAULT nextval('public.departments_id_seq'::regclass);


--
-- Name: depreciation_books id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.depreciation_books ALTER COLUMN id SET DEFAULT nextval('public.depreciation_books_id_seq'::regclass);


--
-- Name: dimension_combinations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_combinations ALTER COLUMN id SET DEFAULT nextval('public.dimension_combinations_id_seq'::regclass);


--
-- Name: dimension_set_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_set_entries ALTER COLUMN id SET DEFAULT nextval('public.dimension_set_entries_id_seq'::regclass);


--
-- Name: dimension_set_tree_nodes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_set_tree_nodes ALTER COLUMN id SET DEFAULT nextval('public.dimension_set_tree_nodes_id_seq'::regclass);


--
-- Name: dimension_sets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_sets ALTER COLUMN id SET DEFAULT nextval('public.dimension_sets_id_seq'::regclass);


--
-- Name: dimension_value_combinations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_value_combinations ALTER COLUMN id SET DEFAULT nextval('public.dimension_value_combinations_id_seq'::regclass);


--
-- Name: dimension_values id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_values ALTER COLUMN id SET DEFAULT nextval('public.dimension_values_id_seq'::regclass);


--
-- Name: dimensions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimensions ALTER COLUMN id SET DEFAULT nextval('public.dimensions_id_seq'::regclass);


--
-- Name: discount_rules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discount_rules ALTER COLUMN id SET DEFAULT nextval('public.discount_rules_id_seq'::regclass);


--
-- Name: document_headers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_headers ALTER COLUMN id SET DEFAULT nextval('public.document_headers_id_seq'::regclass);


--
-- Name: employee_bank_accounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_bank_accounts ALTER COLUMN id SET DEFAULT nextval('public.employee_bank_accounts_id_seq'::regclass);


--
-- Name: employee_compensation id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_compensation ALTER COLUMN id SET DEFAULT nextval('public.employee_compensation_id_seq'::regclass);


--
-- Name: employee_pay_codes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_pay_codes ALTER COLUMN id SET DEFAULT nextval('public.employee_pay_codes_id_seq'::regclass);


--
-- Name: employee_posting_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_posting_groups ALTER COLUMN id SET DEFAULT nextval('public.employee_posting_groups_id_seq'::regclass);


--
-- Name: employee_promotion_histories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_promotion_histories ALTER COLUMN id SET DEFAULT nextval('public.employee_promotion_histories_id_seq'::regclass);


--
-- Name: employee_ytd_balances id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_ytd_balances ALTER COLUMN id SET DEFAULT nextval('public.employee_ytd_balances_id_seq'::regclass);


--
-- Name: employees id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees ALTER COLUMN id SET DEFAULT nextval('public.employees_id_seq'::regclass);


--
-- Name: expense_allocations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_allocations ALTER COLUMN id SET DEFAULT nextval('public.expense_allocations_id_seq'::regclass);


--
-- Name: expense_budgets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_budgets ALTER COLUMN id SET DEFAULT nextval('public.expense_budgets_id_seq'::regclass);


--
-- Name: expense_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_categories ALTER COLUMN id SET DEFAULT nextval('public.expense_categories_id_seq'::regclass);


--
-- Name: expense_transactions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions ALTER COLUMN id SET DEFAULT nextval('public.expense_transactions_id_seq'::regclass);


--
-- Name: fa_classes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_classes ALTER COLUMN id SET DEFAULT nextval('public.fa_classes_id_seq'::regclass);


--
-- Name: fa_insurance_policies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_insurance_policies ALTER COLUMN id SET DEFAULT nextval('public.fa_insurance_policies_id_seq'::regclass);


--
-- Name: fa_journal_batches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_batches ALTER COLUMN id SET DEFAULT nextval('public.fa_journal_batches_id_seq'::regclass);


--
-- Name: fa_journal_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_lines ALTER COLUMN id SET DEFAULT nextval('public.fa_journal_lines_id_seq'::regclass);


--
-- Name: fa_journal_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_templates ALTER COLUMN id SET DEFAULT nextval('public.fa_journal_templates_id_seq'::regclass);


--
-- Name: fa_ledger_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_ledger_entries ALTER COLUMN id SET DEFAULT nextval('public.fa_ledger_entries_id_seq'::regclass);


--
-- Name: fa_locations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_locations ALTER COLUMN id SET DEFAULT nextval('public.fa_locations_id_seq'::regclass);


--
-- Name: fa_maintenance_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_maintenance_logs ALTER COLUMN id SET DEFAULT nextval('public.fa_maintenance_logs_id_seq'::regclass);


--
-- Name: fa_posting_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups ALTER COLUMN id SET DEFAULT nextval('public.fa_posting_groups_id_seq'::regclass);


--
-- Name: fa_subclasses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_subclasses ALTER COLUMN id SET DEFAULT nextval('public.fa_subclasses_id_seq'::regclass);


--
-- Name: factories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.factories ALTER COLUMN id SET DEFAULT nextval('public.factories_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: fiscal_reopen_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fiscal_reopen_logs ALTER COLUMN id SET DEFAULT nextval('public.fiscal_reopen_logs_id_seq'::regclass);


--
-- Name: fixed_asset_journal_batches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_batches ALTER COLUMN id SET DEFAULT nextval('public.fixed_asset_journal_batches_id_seq'::regclass);


--
-- Name: fixed_asset_journal_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_lines ALTER COLUMN id SET DEFAULT nextval('public.fixed_asset_journal_lines_id_seq'::regclass);


--
-- Name: fixed_asset_journal_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_templates ALTER COLUMN id SET DEFAULT nextval('public.fixed_asset_journal_templates_id_seq'::regclass);


--
-- Name: fixed_assets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets ALTER COLUMN id SET DEFAULT nextval('public.fixed_assets_id_seq'::regclass);


--
-- Name: general_business_posting_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_business_posting_groups ALTER COLUMN id SET DEFAULT nextval('public.general_business_posting_groups_id_seq'::regclass);


--
-- Name: general_journal_batches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_batches ALTER COLUMN id SET DEFAULT nextval('public.general_journal_batches_id_seq'::regclass);


--
-- Name: general_journal_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_lines ALTER COLUMN id SET DEFAULT nextval('public.general_journal_lines_id_seq'::regclass);


--
-- Name: general_journal_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_templates ALTER COLUMN id SET DEFAULT nextval('public.general_journal_templates_id_seq'::regclass);


--
-- Name: general_ledger_setup id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_ledger_setup ALTER COLUMN id SET DEFAULT nextval('public.general_ledger_setup_id_seq'::regclass);


--
-- Name: general_posting_setup_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setup_lines ALTER COLUMN id SET DEFAULT nextval('public.general_posting_setup_lines_id_seq'::regclass);


--
-- Name: general_posting_setups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups ALTER COLUMN id SET DEFAULT nextval('public.general_posting_setups_id_seq'::regclass);


--
-- Name: general_product_posting_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_product_posting_groups ALTER COLUMN id SET DEFAULT nextval('public.general_product_posting_groups_id_seq'::regclass);


--
-- Name: gl_accounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gl_accounts ALTER COLUMN id SET DEFAULT nextval('public.gl_accounts_id_seq'::regclass);


--
-- Name: gl_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gl_entries ALTER COLUMN id SET DEFAULT nextval('public.gl_entries_id_seq'::regclass);


--
-- Name: inventory_adjustment_journals id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustment_journals ALTER COLUMN id SET DEFAULT nextval('public.inventory_adjustment_journals_id_seq'::regclass);


--
-- Name: inventory_adjustment_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustment_lines ALTER COLUMN id SET DEFAULT nextval('public.inventory_adjustment_lines_id_seq'::regclass);


--
-- Name: inventory_posting_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_posting_groups ALTER COLUMN id SET DEFAULT nextval('public.inventory_posting_groups_id_seq'::regclass);


--
-- Name: inventory_posting_setups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_posting_setups ALTER COLUMN id SET DEFAULT nextval('public.inventory_posting_setups_id_seq'::regclass);


--
-- Name: inventory_putaway_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_putaway_lines ALTER COLUMN id SET DEFAULT nextval('public.inventory_putaway_lines_id_seq'::regclass);


--
-- Name: inventory_putaways id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_putaways ALTER COLUMN id SET DEFAULT nextval('public.inventory_putaways_id_seq'::regclass);


--
-- Name: item_category_assignments assignment_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_category_assignments ALTER COLUMN assignment_id SET DEFAULT nextval('public.item_category_assignments_assignment_id_seq'::regclass);


--
-- Name: item_charges id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_charges ALTER COLUMN id SET DEFAULT nextval('public.item_charges_id_seq'::regclass);


--
-- Name: item_journal_batches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_batches ALTER COLUMN id SET DEFAULT nextval('public.item_journal_batches_id_seq'::regclass);


--
-- Name: item_journal_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines ALTER COLUMN id SET DEFAULT nextval('public.item_journal_lines_id_seq'::regclass);


--
-- Name: item_journal_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_templates ALTER COLUMN id SET DEFAULT nextval('public.item_journal_templates_id_seq'::regclass);


--
-- Name: item_ledger_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_ledger_entries ALTER COLUMN id SET DEFAULT nextval('public.item_ledger_entries_id_seq'::regclass);


--
-- Name: item_lots id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_lots ALTER COLUMN id SET DEFAULT nextval('public.item_lots_id_seq'::regclass);


--
-- Name: item_skus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_skus ALTER COLUMN id SET DEFAULT nextval('public.item_skus_id_seq'::regclass);


--
-- Name: item_tracking_codes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_tracking_codes ALTER COLUMN id SET DEFAULT nextval('public.item_tracking_codes_id_seq'::regclass);


--
-- Name: item_tracking_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_tracking_lines ALTER COLUMN id SET DEFAULT nextval('public.item_tracking_lines_id_seq'::regclass);


--
-- Name: item_uom_assignments assignment_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_uom_assignments ALTER COLUMN assignment_id SET DEFAULT nextval('public.item_uom_assignments_assignment_id_seq'::regclass);


--
-- Name: items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items ALTER COLUMN id SET DEFAULT nextval('public.items_id_seq'::regclass);


--
-- Name: job_journal_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_journal_lines ALTER COLUMN id SET DEFAULT nextval('public.job_journal_lines_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: journal_batches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_batches ALTER COLUMN id SET DEFAULT nextval('public.journal_batches_id_seq'::regclass);


--
-- Name: journal_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_lines ALTER COLUMN id SET DEFAULT nextval('public.journal_lines_id_seq'::regclass);


--
-- Name: journal_posting_services id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_posting_services ALTER COLUMN id SET DEFAULT nextval('public.journal_posting_services_id_seq'::regclass);


--
-- Name: journal_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_templates ALTER COLUMN id SET DEFAULT nextval('public.journal_templates_id_seq'::regclass);


--
-- Name: locations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locations ALTER COLUMN id SET DEFAULT nextval('public.locations_id_seq'::regclass);


--
-- Name: machine_centers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.machine_centers ALTER COLUMN id SET DEFAULT nextval('public.machine_centers_id_seq'::regclass);


--
-- Name: maintenance_contract_assets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_assets ALTER COLUMN id SET DEFAULT nextval('public.maintenance_contract_assets_id_seq'::regclass);


--
-- Name: maintenance_contract_billings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_billings ALTER COLUMN id SET DEFAULT nextval('public.maintenance_contract_billings_id_seq'::regclass);


--
-- Name: maintenance_contract_schedules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_schedules ALTER COLUMN id SET DEFAULT nextval('public.maintenance_contract_schedules_id_seq'::regclass);


--
-- Name: maintenance_contracts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts ALTER COLUMN id SET DEFAULT nextval('public.maintenance_contracts_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: number_series id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_series ALTER COLUMN id SET DEFAULT nextval('public.number_series_id_seq'::regclass);


--
-- Name: number_series_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_series_lines ALTER COLUMN id SET DEFAULT nextval('public.number_series_lines_id_seq'::regclass);


--
-- Name: overhead_cost_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.overhead_cost_categories ALTER COLUMN id SET DEFAULT nextval('public.overhead_cost_categories_id_seq'::regclass);


--
-- Name: pay_codes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pay_codes ALTER COLUMN id SET DEFAULT nextval('public.pay_codes_id_seq'::regclass);


--
-- Name: payment_applications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_applications ALTER COLUMN id SET DEFAULT nextval('public.payment_applications_id_seq'::regclass);


--
-- Name: payment_journal_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_journal_lines ALTER COLUMN id SET DEFAULT nextval('public.payment_journal_lines_id_seq'::regclass);


--
-- Name: payment_terms id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_terms ALTER COLUMN id SET DEFAULT nextval('public.payment_terms_id_seq'::regclass);


--
-- Name: payments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments ALTER COLUMN id SET DEFAULT nextval('public.payments_id_seq'::regclass);


--
-- Name: payroll_documents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_documents ALTER COLUMN id SET DEFAULT nextval('public.payroll_documents_id_seq'::regclass);


--
-- Name: payroll_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_lines ALTER COLUMN id SET DEFAULT nextval('public.payroll_lines_id_seq'::regclass);


--
-- Name: payroll_periods id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_periods ALTER COLUMN id SET DEFAULT nextval('public.payroll_periods_id_seq'::regclass);


--
-- Name: payroll_posting_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_posting_groups ALTER COLUMN id SET DEFAULT nextval('public.payroll_posting_groups_id_seq'::regclass);


--
-- Name: payroll_statutory_setups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_statutory_setups ALTER COLUMN id SET DEFAULT nextval('public.payroll_statutory_setups_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: physical_inventory_journals id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.physical_inventory_journals ALTER COLUMN id SET DEFAULT nextval('public.physical_inventory_journals_id_seq'::regclass);


--
-- Name: physical_inventory_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.physical_inventory_lines ALTER COLUMN id SET DEFAULT nextval('public.physical_inventory_lines_id_seq'::regclass);


--
-- Name: posted_purchase_credit_memo_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memo_lines ALTER COLUMN id SET DEFAULT nextval('public.posted_purchase_credit_memo_lines_id_seq'::regclass);


--
-- Name: posted_purchase_credit_memos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memos ALTER COLUMN id SET DEFAULT nextval('public.posted_purchase_credit_memos_id_seq'::regclass);


--
-- Name: posted_purchase_invoice_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoice_lines ALTER COLUMN id SET DEFAULT nextval('public.posted_purchase_invoice_lines_id_seq'::regclass);


--
-- Name: posted_purchase_invoices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoices ALTER COLUMN id SET DEFAULT nextval('public.posted_purchase_invoices_id_seq'::regclass);


--
-- Name: posted_sales_credit_memo_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memo_lines ALTER COLUMN id SET DEFAULT nextval('public.posted_sales_credit_memo_lines_id_seq'::regclass);


--
-- Name: posted_sales_credit_memos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memos ALTER COLUMN id SET DEFAULT nextval('public.posted_sales_credit_memos_id_seq'::regclass);


--
-- Name: posted_sales_invoice_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoice_lines ALTER COLUMN id SET DEFAULT nextval('public.posted_sales_invoice_lines_id_seq'::regclass);


--
-- Name: posted_sales_invoices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoices ALTER COLUMN id SET DEFAULT nextval('public.posted_sales_invoices_id_seq'::regclass);


--
-- Name: price_change_template_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.price_change_template_lines ALTER COLUMN id SET DEFAULT nextval('public.price_change_template_items_id_seq'::regclass);


--
-- Name: price_change_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.price_change_templates ALTER COLUMN id SET DEFAULT nextval('public.price_change_templates_id_seq'::regclass);


--
-- Name: price_lists id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.price_lists ALTER COLUMN id SET DEFAULT nextval('public.price_lists_id_seq'::regclass);


--
-- Name: pricing_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_groups ALTER COLUMN id SET DEFAULT nextval('public.pricing_groups_id_seq'::regclass);


--
-- Name: pricing_master id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_master ALTER COLUMN id SET DEFAULT nextval('public.pricing_master_id_seq'::regclass);


--
-- Name: pricing_master_quantity_breaks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_master_quantity_breaks ALTER COLUMN id SET DEFAULT nextval('public.pricing_master_quantity_breaks_id_seq'::regclass);


--
-- Name: production_bom_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_lines ALTER COLUMN id SET DEFAULT nextval('public.production_bom_lines_id_seq'::regclass);


--
-- Name: production_bom_version_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_version_lines ALTER COLUMN id SET DEFAULT nextval('public.production_bom_version_lines_id_seq'::regclass);


--
-- Name: production_bom_versions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_versions ALTER COLUMN id SET DEFAULT nextval('public.production_bom_versions_id_seq'::regclass);


--
-- Name: production_boms id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_boms ALTER COLUMN id SET DEFAULT nextval('public.production_boms_id_seq'::regclass);


--
-- Name: production_journal_batches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_batches ALTER COLUMN id SET DEFAULT nextval('public.production_journal_batches_id_seq'::regclass);


--
-- Name: production_journal_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines ALTER COLUMN id SET DEFAULT nextval('public.production_journal_lines_id_seq'::regclass);


--
-- Name: production_journal_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_templates ALTER COLUMN id SET DEFAULT nextval('public.production_journal_templates_id_seq'::regclass);


--
-- Name: production_order_components id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_components ALTER COLUMN id SET DEFAULT nextval('public.production_order_components_id_seq'::regclass);


--
-- Name: production_order_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_lines ALTER COLUMN id SET DEFAULT nextval('public.production_order_lines_id_seq'::regclass);


--
-- Name: production_order_routing_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_routing_lines ALTER COLUMN id SET DEFAULT nextval('public.production_order_routing_lines_id_seq'::regclass);


--
-- Name: production_orders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders ALTER COLUMN id SET DEFAULT nextval('public.production_orders_id_seq'::regclass);


--
-- Name: purchase_credit_memo_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memo_lines ALTER COLUMN id SET DEFAULT nextval('public.purchase_credit_memo_lines_id_seq'::regclass);


--
-- Name: purchase_credit_memos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memos ALTER COLUMN id SET DEFAULT nextval('public.purchase_credit_memos_id_seq'::regclass);


--
-- Name: purchase_invoice_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoice_lines ALTER COLUMN id SET DEFAULT nextval('public.purchase_invoice_lines_id_seq'::regclass);


--
-- Name: purchase_invoices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoices ALTER COLUMN id SET DEFAULT nextval('public.purchase_invoices_id_seq'::regclass);


--
-- Name: purchase_order_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_lines ALTER COLUMN id SET DEFAULT nextval('public.purchase_order_lines_id_seq'::regclass);


--
-- Name: purchase_orders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders ALTER COLUMN id SET DEFAULT nextval('public.purchase_orders_id_seq'::regclass);


--
-- Name: purchase_prices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_prices ALTER COLUMN id SET DEFAULT nextval('public.purchase_prices_id_seq'::regclass);


--
-- Name: purchase_quote_approval_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_approval_entries ALTER COLUMN id SET DEFAULT nextval('public.purchase_quote_approval_entries_id_seq'::regclass);


--
-- Name: purchase_quote_archives id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_archives ALTER COLUMN id SET DEFAULT nextval('public.purchase_quote_archives_id_seq'::regclass);


--
-- Name: purchase_quote_line_archives id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_line_archives ALTER COLUMN id SET DEFAULT nextval('public.purchase_quote_line_archives_id_seq'::regclass);


--
-- Name: purchase_quote_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_lines ALTER COLUMN id SET DEFAULT nextval('public.purchase_quote_lines_id_seq'::regclass);


--
-- Name: purchase_quotes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quotes ALTER COLUMN id SET DEFAULT nextval('public.purchase_quotes_id_seq'::regclass);


--
-- Name: purchase_receipt_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipt_lines ALTER COLUMN id SET DEFAULT nextval('public.purchase_receipt_lines_id_seq'::regclass);


--
-- Name: purchase_receipts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipts ALTER COLUMN id SET DEFAULT nextval('public.purchase_receipts_id_seq'::regclass);


--
-- Name: putaway_worksheet_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.putaway_worksheet_lines ALTER COLUMN id SET DEFAULT nextval('public.putaway_worksheet_lines_id_seq'::regclass);


--
-- Name: putaway_worksheets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.putaway_worksheets ALTER COLUMN id SET DEFAULT nextval('public.putaway_worksheets_id_seq'::regclass);


--
-- Name: reason_codes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reason_codes ALTER COLUMN id SET DEFAULT nextval('public.reason_codes_id_seq'::regclass);


--
-- Name: recurring_expenses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_expenses ALTER COLUMN id SET DEFAULT nextval('public.recurring_expenses_id_seq'::regclass);


--
-- Name: recurring_journal_batches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_batches ALTER COLUMN id SET DEFAULT nextval('public.recurring_journal_batches_id_seq'::regclass);


--
-- Name: recurring_journal_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_lines ALTER COLUMN id SET DEFAULT nextval('public.recurring_journal_lines_id_seq'::regclass);


--
-- Name: recurring_journal_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_templates ALTER COLUMN id SET DEFAULT nextval('public.recurring_journal_templates_id_seq'::regclass);


--
-- Name: reservation_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservation_entries ALTER COLUMN id SET DEFAULT nextval('public.reservation_entries_id_seq'::regclass);


--
-- Name: resource_journal_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resource_journal_lines ALTER COLUMN id SET DEFAULT nextval('public.resource_journal_lines_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: routing_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_lines ALTER COLUMN id SET DEFAULT nextval('public.routing_lines_id_seq'::regclass);


--
-- Name: routing_version_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_version_lines ALTER COLUMN id SET DEFAULT nextval('public.routing_version_lines_id_seq'::regclass);


--
-- Name: routing_versions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_versions ALTER COLUMN id SET DEFAULT nextval('public.routing_versions_id_seq'::regclass);


--
-- Name: routings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routings ALTER COLUMN id SET DEFAULT nextval('public.routings_id_seq'::regclass);


--
-- Name: sales_credit_memo_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_credit_memo_lines ALTER COLUMN id SET DEFAULT nextval('public.sales_credit_memo_lines_id_seq'::regclass);


--
-- Name: sales_credit_memos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_credit_memos ALTER COLUMN id SET DEFAULT nextval('public.sales_credit_memos_id_seq'::regclass);


--
-- Name: sales_invoice_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_invoice_lines ALTER COLUMN id SET DEFAULT nextval('public.sales_invoice_lines_id_seq'::regclass);


--
-- Name: sales_invoices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_invoices ALTER COLUMN id SET DEFAULT nextval('public.sales_invoices_id_seq'::regclass);


--
-- Name: sales_order_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_order_lines ALTER COLUMN id SET DEFAULT nextval('public.sales_order_lines_id_seq'::regclass);


--
-- Name: sales_orders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders ALTER COLUMN id SET DEFAULT nextval('public.sales_orders_id_seq'::regclass);


--
-- Name: sales_quote_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_quote_items ALTER COLUMN id SET DEFAULT nextval('public.sales_quote_items_id_seq'::regclass);


--
-- Name: sales_quote_revisions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_quote_revisions ALTER COLUMN id SET DEFAULT nextval('public.sales_quote_revisions_id_seq'::regclass);


--
-- Name: sales_quotes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_quotes ALTER COLUMN id SET DEFAULT nextval('public.sales_quotes_id_seq'::regclass);


--
-- Name: sales_shipment_headers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_shipment_headers ALTER COLUMN id SET DEFAULT nextval('public.sales_shipment_headers_id_seq'::regclass);


--
-- Name: sales_shipment_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_shipment_lines ALTER COLUMN id SET DEFAULT nextval('public.sales_shipment_lines_id_seq'::regclass);


--
-- Name: shipment_methods id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipment_methods ALTER COLUMN id SET DEFAULT nextval('public.shipment_methods_id_seq'::regclass);


--
-- Name: shipping_agent_services id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_agent_services ALTER COLUMN id SET DEFAULT nextval('public.shipping_agent_services_id_seq'::regclass);


--
-- Name: shipping_agents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_agents ALTER COLUMN id SET DEFAULT nextval('public.shipping_agents_id_seq'::regclass);


--
-- Name: social_security_tiers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_security_tiers ALTER COLUMN id SET DEFAULT nextval('public.social_security_tiers_id_seq'::regclass);


--
-- Name: sync_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sync_logs ALTER COLUMN id SET DEFAULT nextval('public.sync_logs_id_seq'::regclass);


--
-- Name: tax_brackets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_brackets ALTER COLUMN id SET DEFAULT nextval('public.tax_brackets_id_seq'::regclass);


--
-- Name: tax_tables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_tables ALTER COLUMN id SET DEFAULT nextval('public.tax_tables_id_seq'::regclass);


--
-- Name: unit_of_measures id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unit_of_measures ALTER COLUMN id SET DEFAULT nextval('public.unit_of_measures_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: value_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.value_entries ALTER COLUMN id SET DEFAULT nextval('public.value_entries_id_seq'::regclass);


--
-- Name: vat_business_posting_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_business_posting_groups ALTER COLUMN id SET DEFAULT nextval('public.vat_business_posting_groups_id_seq'::regclass);


--
-- Name: vat_masters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_masters ALTER COLUMN id SET DEFAULT nextval('public.vat_masters_id_seq'::regclass);


--
-- Name: vat_posting_setups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_posting_setups ALTER COLUMN id SET DEFAULT nextval('public.vat_posting_setups_id_seq'::regclass);


--
-- Name: vat_product_posting_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_product_posting_groups ALTER COLUMN id SET DEFAULT nextval('public.vat_product_posting_groups_id_seq'::regclass);


--
-- Name: vendor_invoice_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoice_lines ALTER COLUMN id SET DEFAULT nextval('public.vendor_invoice_lines_id_seq'::regclass);


--
-- Name: vendor_invoices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoices ALTER COLUMN id SET DEFAULT nextval('public.vendor_invoices_id_seq'::regclass);


--
-- Name: vendor_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_items ALTER COLUMN id SET DEFAULT nextval('public.vendor_items_id_seq'::regclass);


--
-- Name: vendor_ledger_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_ledger_entries ALTER COLUMN id SET DEFAULT nextval('public.vendor_ledger_entries_id_seq'::regclass);


--
-- Name: vendor_posting_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_posting_groups ALTER COLUMN id SET DEFAULT nextval('public.vendor_posting_groups_id_seq'::regclass);


--
-- Name: vendors id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors ALTER COLUMN id SET DEFAULT nextval('public.vendors_id_seq'::regclass);


--
-- Name: warehouse_activities id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activities ALTER COLUMN id SET DEFAULT nextval('public.warehouse_activities_id_seq'::regclass);


--
-- Name: warehouse_activity_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activity_lines ALTER COLUMN id SET DEFAULT nextval('public.warehouse_activity_lines_id_seq'::regclass);


--
-- Name: warehouse_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_entries ALTER COLUMN id SET DEFAULT nextval('public.warehouse_entries_id_seq'::regclass);


--
-- Name: warehouse_journal_batches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_batches ALTER COLUMN id SET DEFAULT nextval('public.warehouse_journal_batches_id_seq'::regclass);


--
-- Name: warehouse_journal_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines ALTER COLUMN id SET DEFAULT nextval('public.warehouse_journal_lines_id_seq'::regclass);


--
-- Name: warehouse_journal_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_templates ALTER COLUMN id SET DEFAULT nextval('public.warehouse_journal_templates_id_seq'::regclass);


--
-- Name: warehouse_pick_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_pick_lines ALTER COLUMN id SET DEFAULT nextval('public.warehouse_pick_lines_id_seq'::regclass);


--
-- Name: warehouse_picks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_picks ALTER COLUMN id SET DEFAULT nextval('public.warehouse_picks_id_seq'::regclass);


--
-- Name: warehouse_putaway_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_putaway_lines ALTER COLUMN id SET DEFAULT nextval('public.warehouse_putaway_lines_id_seq'::regclass);


--
-- Name: warehouse_putaways id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_putaways ALTER COLUMN id SET DEFAULT nextval('public.warehouse_putaways_id_seq'::regclass);


--
-- Name: warehouse_receipt_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_receipt_lines ALTER COLUMN id SET DEFAULT nextval('public.warehouse_receipt_lines_id_seq'::regclass);


--
-- Name: warehouse_receipts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_receipts ALTER COLUMN id SET DEFAULT nextval('public.warehouse_receipts_id_seq'::regclass);


--
-- Name: warehouse_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_requests ALTER COLUMN id SET DEFAULT nextval('public.warehouse_requests_id_seq'::regclass);


--
-- Name: warehouse_setup id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_setup ALTER COLUMN id SET DEFAULT nextval('public.warehouse_setup_id_seq'::regclass);


--
-- Name: warehouse_shipment_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_shipment_lines ALTER COLUMN id SET DEFAULT nextval('public.warehouse_shipment_lines_id_seq'::regclass);


--
-- Name: warehouse_shipments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_shipments ALTER COLUMN id SET DEFAULT nextval('public.warehouse_shipments_id_seq'::regclass);


--
-- Name: work_center_bins id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_bins ALTER COLUMN id SET DEFAULT nextval('public.work_center_bins_id_seq'::regclass);


--
-- Name: work_center_calendars id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_calendars ALTER COLUMN id SET DEFAULT nextval('public.work_center_calendars_id_seq'::regclass);


--
-- Name: work_center_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_groups ALTER COLUMN id SET DEFAULT nextval('public.work_center_groups_id_seq'::regclass);


--
-- Name: work_centers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_centers ALTER COLUMN id SET DEFAULT nextval('public.work_centers_id_seq'::regclass);


--
-- Name: zones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.zones ALTER COLUMN id SET DEFAULT nextval('public.zones_id_seq'::regclass);


--
-- Name: account_schedule_lines account_schedule_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_schedule_lines
    ADD CONSTRAINT account_schedule_lines_pkey PRIMARY KEY (id);


--
-- Name: account_schedules account_schedules_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_schedules
    ADD CONSTRAINT account_schedules_name_unique UNIQUE (name);


--
-- Name: account_schedules account_schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_schedules
    ADD CONSTRAINT account_schedules_pkey PRIMARY KEY (id);


--
-- Name: accounting_periods accounting_periods_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accounting_periods
    ADD CONSTRAINT accounting_periods_pkey PRIMARY KEY (id);


--
-- Name: accounting_periods accounting_periods_start_date_end_date_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accounting_periods
    ADD CONSTRAINT accounting_periods_start_date_end_date_unique UNIQUE (start_date, end_date);


--
-- Name: actual_overhead_costs actual_overhead_costs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actual_overhead_costs
    ADD CONSTRAINT actual_overhead_costs_pkey PRIMARY KEY (id);


--
-- Name: allocation_lines allocation_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocation_lines
    ADD CONSTRAINT allocation_lines_pkey PRIMARY KEY (id);


--
-- Name: allocations allocations_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocations
    ADD CONSTRAINT allocations_code_unique UNIQUE (code);


--
-- Name: allocations allocations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocations
    ADD CONSTRAINT allocations_pkey PRIMARY KEY (id);


--
-- Name: approval_entries approval_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_entries
    ADD CONSTRAINT approval_entries_pkey PRIMARY KEY (id);


--
-- Name: approval_template_entries approval_template_entries_approval_template_id_sequence_no_uniq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_template_entries
    ADD CONSTRAINT approval_template_entries_approval_template_id_sequence_no_uniq UNIQUE (approval_template_id, sequence_no);


--
-- Name: approval_template_entries approval_template_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_template_entries
    ADD CONSTRAINT approval_template_entries_pkey PRIMARY KEY (id);


--
-- Name: approval_templates approval_templates_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_templates
    ADD CONSTRAINT approval_templates_code_unique UNIQUE (code);


--
-- Name: approval_templates approval_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_templates
    ADD CONSTRAINT approval_templates_pkey PRIMARY KEY (id);


--
-- Name: asset_components asset_components_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_components
    ADD CONSTRAINT asset_components_pkey PRIMARY KEY (id);


--
-- Name: asset_ledger_entries asset_ledger_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_ledger_entries
    ADD CONSTRAINT asset_ledger_entries_pkey PRIMARY KEY (id);


--
-- Name: asset_maintenances asset_maintenances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_maintenances
    ADD CONSTRAINT asset_maintenances_pkey PRIMARY KEY (id);


--
-- Name: assets assets_asset_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_asset_no_unique UNIQUE (asset_no);


--
-- Name: assets assets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_pkey PRIMARY KEY (id);


--
-- Name: attendance_ledger_entries attendance_ledger_entries_employee_id_attendance_date_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_ledger_entries
    ADD CONSTRAINT attendance_ledger_entries_employee_id_attendance_date_unique UNIQUE (employee_id, attendance_date);


--
-- Name: attendance_ledger_entries attendance_ledger_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_ledger_entries
    ADD CONSTRAINT attendance_ledger_entries_pkey PRIMARY KEY (id);


--
-- Name: bank_account_ledger_entries bank_account_ledger_entries_entry_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_ledger_entries
    ADD CONSTRAINT bank_account_ledger_entries_entry_number_unique UNIQUE (entry_number);


--
-- Name: bank_account_ledger_entries bank_account_ledger_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_ledger_entries
    ADD CONSTRAINT bank_account_ledger_entries_pkey PRIMARY KEY (id);


--
-- Name: bank_account_statement_lines bank_account_statement_lines_bank_account_id_statement_no_state; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_statement_lines
    ADD CONSTRAINT bank_account_statement_lines_bank_account_id_statement_no_state UNIQUE (bank_account_id, statement_no, statement_line_no);


--
-- Name: bank_account_statement_lines bank_account_statement_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_statement_lines
    ADD CONSTRAINT bank_account_statement_lines_pkey PRIMARY KEY (id);


--
-- Name: bank_accounts bank_accounts_account_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_accounts
    ADD CONSTRAINT bank_accounts_account_code_unique UNIQUE (account_code);


--
-- Name: bank_accounts bank_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_accounts
    ADD CONSTRAINT bank_accounts_pkey PRIMARY KEY (id);


--
-- Name: bank_reconciliations bank_reconciliations_bank_account_id_statement_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_reconciliations
    ADD CONSTRAINT bank_reconciliations_bank_account_id_statement_no_unique UNIQUE (bank_account_id, statement_no);


--
-- Name: bank_reconciliations bank_reconciliations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_reconciliations
    ADD CONSTRAINT bank_reconciliations_pkey PRIMARY KEY (id);


--
-- Name: bin_contents bin_contents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bin_contents
    ADD CONSTRAINT bin_contents_pkey PRIMARY KEY (id);


--
-- Name: bins bins_location_id_bin_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bins
    ADD CONSTRAINT bins_location_id_bin_code_unique UNIQUE (location_id, bin_code);


--
-- Name: bins bins_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bins
    ADD CONSTRAINT bins_pkey PRIMARY KEY (id);


--
-- Name: blanket_order_lines blanket_order_lines_blanket_order_id_line_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_order_lines
    ADD CONSTRAINT blanket_order_lines_blanket_order_id_line_number_unique UNIQUE (blanket_order_id, line_number);


--
-- Name: blanket_order_lines blanket_order_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_order_lines
    ADD CONSTRAINT blanket_order_lines_pkey PRIMARY KEY (id);


--
-- Name: blanket_orders blanket_orders_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_orders
    ADD CONSTRAINT blanket_orders_document_number_unique UNIQUE (document_number);


--
-- Name: blanket_orders blanket_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_orders
    ADD CONSTRAINT blanket_orders_pkey PRIMARY KEY (id);


--
-- Name: production_bom_versions bom_version_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_versions
    ADD CONSTRAINT bom_version_unique UNIQUE (production_bom_id, version_code);


--
-- Name: expense_budgets budget_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_budgets
    ADD CONSTRAINT budget_unique UNIQUE (fiscal_year, account_type, category_code, shortcut_dimension_1_code, shortcut_dimension_2_code);


--
-- Name: business_units business_units_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.business_units
    ADD CONSTRAINT business_units_code_unique UNIQUE (code);


--
-- Name: business_units business_units_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.business_units
    ADD CONSTRAINT business_units_pkey PRIMARY KEY (id);


--
-- Name: businesses businesses_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.businesses
    ADD CONSTRAINT businesses_code_unique UNIQUE (code);


--
-- Name: businesses businesses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.businesses
    ADD CONSTRAINT businesses_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: campaign_items campaign_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaign_items
    ADD CONSTRAINT campaign_items_pkey PRIMARY KEY (id);


--
-- Name: campaigns campaigns_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaigns
    ADD CONSTRAINT campaigns_pkey PRIMARY KEY (id);


--
-- Name: capacity_ledger_entries capacity_ledger_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capacity_ledger_entries
    ADD CONSTRAINT capacity_ledger_entries_pkey PRIMARY KEY (id);


--
-- Name: capex_project_lines capex_project_lines_capex_project_id_line_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_project_lines
    ADD CONSTRAINT capex_project_lines_capex_project_id_line_number_unique UNIQUE (capex_project_id, line_number);


--
-- Name: capex_project_lines capex_project_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_project_lines
    ADD CONSTRAINT capex_project_lines_pkey PRIMARY KEY (id);


--
-- Name: capex_projects capex_projects_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_projects
    ADD CONSTRAINT capex_projects_pkey PRIMARY KEY (id);


--
-- Name: capex_projects capex_projects_project_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_projects
    ADD CONSTRAINT capex_projects_project_number_unique UNIQUE (project_number);


--
-- Name: cash_receipt_lines cash_receipt_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cash_receipt_lines
    ADD CONSTRAINT cash_receipt_lines_pkey PRIMARY KEY (id);


--
-- Name: categories categories_category_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_category_code_unique UNIQUE (category_code);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: chart_of_accounts chart_of_accounts_account_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_account_number_unique UNIQUE (account_number);


--
-- Name: chart_of_accounts chart_of_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_pkey PRIMARY KEY (id);


--
-- Name: company_information company_information_business_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_information
    ADD CONSTRAINT company_information_business_id_unique UNIQUE (business_id);


--
-- Name: company_information company_information_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_information
    ADD CONSTRAINT company_information_pkey PRIMARY KEY (id);


--
-- Name: contacts contacts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contacts
    ADD CONSTRAINT contacts_pkey PRIMARY KEY (id);


--
-- Name: currencies currencies_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_code_unique UNIQUE (code);


--
-- Name: currencies currencies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_pkey PRIMARY KEY (id);


--
-- Name: currency_adjustment_ledger currency_adjustment_ledger_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_adjustment_ledger
    ADD CONSTRAINT currency_adjustment_ledger_pkey PRIMARY KEY (id);


--
-- Name: currency_buffers currency_buffers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_buffers
    ADD CONSTRAINT currency_buffers_pkey PRIMARY KEY (id);


--
-- Name: currency_exchange_rates currency_exchange_rates_currency_id_starting_date_rate_type_uni; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_exchange_rates
    ADD CONSTRAINT currency_exchange_rates_currency_id_starting_date_rate_type_uni UNIQUE (currency_id, starting_date, rate_type);


--
-- Name: currency_exchange_rates currency_exchange_rates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_exchange_rates
    ADD CONSTRAINT currency_exchange_rates_pkey PRIMARY KEY (id);


--
-- Name: customer_groups customer_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_groups
    ADD CONSTRAINT customer_groups_code_unique UNIQUE (code);


--
-- Name: customer_groups customer_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_groups
    ADD CONSTRAINT customer_groups_pkey PRIMARY KEY (id);


--
-- Name: customer_ledger_entries customer_ledger_entries_customer_id_entry_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_ledger_entries
    ADD CONSTRAINT customer_ledger_entries_customer_id_entry_number_unique UNIQUE (customer_id, entry_number);


--
-- Name: customer_ledger_entries customer_ledger_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_ledger_entries
    ADD CONSTRAINT customer_ledger_entries_pkey PRIMARY KEY (id);


--
-- Name: customer_posting_groups customer_posting_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_posting_groups
    ADD CONSTRAINT customer_posting_groups_code_unique UNIQUE (code);


--
-- Name: customer_posting_groups customer_posting_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_posting_groups
    ADD CONSTRAINT customer_posting_groups_pkey PRIMARY KEY (id);


--
-- Name: customer_price_overrides customer_price_overrides_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_price_overrides
    ADD CONSTRAINT customer_price_overrides_pkey PRIMARY KEY (id);


--
-- Name: customers customers_customer_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_customer_number_unique UNIQUE (customer_number);


--
-- Name: customers customers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_pkey PRIMARY KEY (id);


--
-- Name: default_dimensions default_dimensions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.default_dimensions
    ADD CONSTRAINT default_dimensions_pkey PRIMARY KEY (id);


--
-- Name: default_dimensions default_dimensions_table_id_no_dimension_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.default_dimensions
    ADD CONSTRAINT default_dimensions_table_id_no_dimension_code_unique UNIQUE (table_id, no, dimension_code);


--
-- Name: department_employee department_employee_department_id_employee_id_assignment_type_u; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.department_employee
    ADD CONSTRAINT department_employee_department_id_employee_id_assignment_type_u UNIQUE (department_id, employee_id, assignment_type);


--
-- Name: department_employee department_employee_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.department_employee
    ADD CONSTRAINT department_employee_pkey PRIMARY KEY (id);


--
-- Name: departments departments_department_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_department_code_unique UNIQUE (department_code);


--
-- Name: departments departments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_pkey PRIMARY KEY (id);


--
-- Name: depreciation_books depreciation_books_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.depreciation_books
    ADD CONSTRAINT depreciation_books_code_unique UNIQUE (code);


--
-- Name: depreciation_books depreciation_books_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.depreciation_books
    ADD CONSTRAINT depreciation_books_pkey PRIMARY KEY (id);


--
-- Name: dimension_combinations dimension_combinations_dimension_1_code_dimension_2_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_combinations
    ADD CONSTRAINT dimension_combinations_dimension_1_code_dimension_2_code_unique UNIQUE (dimension_1_code, dimension_2_code);


--
-- Name: dimension_combinations dimension_combinations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_combinations
    ADD CONSTRAINT dimension_combinations_pkey PRIMARY KEY (id);


--
-- Name: dimension_set_entries dimension_set_entries_dimension_set_id_dimension_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_set_entries
    ADD CONSTRAINT dimension_set_entries_dimension_set_id_dimension_code_unique UNIQUE (dimension_set_id, dimension_code);


--
-- Name: dimension_set_entries dimension_set_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_set_entries
    ADD CONSTRAINT dimension_set_entries_pkey PRIMARY KEY (id);


--
-- Name: dimension_set_tree_nodes dimension_set_tree_nodes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_set_tree_nodes
    ADD CONSTRAINT dimension_set_tree_nodes_pkey PRIMARY KEY (id);


--
-- Name: dimension_sets dimension_sets_dimension_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_sets
    ADD CONSTRAINT dimension_sets_dimension_hash_unique UNIQUE (dimension_hash);


--
-- Name: dimension_sets dimension_sets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_sets
    ADD CONSTRAINT dimension_sets_pkey PRIMARY KEY (id);


--
-- Name: dimension_value_combinations dimension_value_combinations_dimension_combination_id_dimension; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_value_combinations
    ADD CONSTRAINT dimension_value_combinations_dimension_combination_id_dimension UNIQUE (dimension_combination_id, dimension_1_value_code, dimension_2_value_code);


--
-- Name: dimension_value_combinations dimension_value_combinations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_value_combinations
    ADD CONSTRAINT dimension_value_combinations_pkey PRIMARY KEY (id);


--
-- Name: dimension_values dimension_values_dimension_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_values
    ADD CONSTRAINT dimension_values_dimension_id_code_unique UNIQUE (dimension_id, code);


--
-- Name: dimension_values dimension_values_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_values
    ADD CONSTRAINT dimension_values_pkey PRIMARY KEY (id);


--
-- Name: dimensions dimensions_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimensions
    ADD CONSTRAINT dimensions_code_unique UNIQUE (code);


--
-- Name: dimensions dimensions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimensions
    ADD CONSTRAINT dimensions_pkey PRIMARY KEY (id);


--
-- Name: discount_rules discount_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discount_rules
    ADD CONSTRAINT discount_rules_pkey PRIMARY KEY (id);


--
-- Name: document_headers document_headers_doc_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_headers
    ADD CONSTRAINT document_headers_doc_no_unique UNIQUE (doc_no);


--
-- Name: document_headers document_headers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_headers
    ADD CONSTRAINT document_headers_pkey PRIMARY KEY (id);


--
-- Name: employee_pay_codes emp_pay_code_effective_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_pay_codes
    ADD CONSTRAINT emp_pay_code_effective_unique UNIQUE (employee_id, pay_code_id, effective_date);


--
-- Name: employee_bank_accounts employee_bank_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_bank_accounts
    ADD CONSTRAINT employee_bank_accounts_pkey PRIMARY KEY (id);


--
-- Name: employee_compensation employee_compensation_employee_id_effective_date_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_compensation
    ADD CONSTRAINT employee_compensation_employee_id_effective_date_unique UNIQUE (employee_id, effective_date);


--
-- Name: employee_compensation employee_compensation_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_compensation
    ADD CONSTRAINT employee_compensation_pkey PRIMARY KEY (id);


--
-- Name: employee_pay_codes employee_pay_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_pay_codes
    ADD CONSTRAINT employee_pay_codes_pkey PRIMARY KEY (id);


--
-- Name: employee_posting_groups employee_posting_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_posting_groups
    ADD CONSTRAINT employee_posting_groups_code_unique UNIQUE (code);


--
-- Name: employee_posting_groups employee_posting_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_posting_groups
    ADD CONSTRAINT employee_posting_groups_pkey PRIMARY KEY (id);


--
-- Name: employee_promotion_histories employee_promotion_histories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_promotion_histories
    ADD CONSTRAINT employee_promotion_histories_pkey PRIMARY KEY (id);


--
-- Name: employee_ytd_balances employee_ytd_balances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_ytd_balances
    ADD CONSTRAINT employee_ytd_balances_pkey PRIMARY KEY (id);


--
-- Name: employees employees_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_email_unique UNIQUE (email);


--
-- Name: employees employees_employee_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_employee_number_unique UNIQUE (employee_number);


--
-- Name: employees employees_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_pkey PRIMARY KEY (id);


--
-- Name: expense_allocations expense_allocations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_allocations
    ADD CONSTRAINT expense_allocations_pkey PRIMARY KEY (id);


--
-- Name: expense_budgets expense_budgets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_budgets
    ADD CONSTRAINT expense_budgets_pkey PRIMARY KEY (id);


--
-- Name: expense_categories expense_categories_account_type_category_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_categories
    ADD CONSTRAINT expense_categories_account_type_category_code_unique UNIQUE (account_type, category_code);


--
-- Name: expense_categories expense_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_categories
    ADD CONSTRAINT expense_categories_pkey PRIMARY KEY (id);


--
-- Name: expense_transactions expense_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_pkey PRIMARY KEY (id);


--
-- Name: fa_classes fa_classes_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_classes
    ADD CONSTRAINT fa_classes_code_unique UNIQUE (code);


--
-- Name: fa_classes fa_classes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_classes
    ADD CONSTRAINT fa_classes_pkey PRIMARY KEY (id);


--
-- Name: fa_insurance_policies fa_insurance_policies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_insurance_policies
    ADD CONSTRAINT fa_insurance_policies_pkey PRIMARY KEY (id);


--
-- Name: fa_journal_batches fa_journal_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_batches
    ADD CONSTRAINT fa_journal_batches_pkey PRIMARY KEY (id);


--
-- Name: fa_journal_batches fa_journal_batches_template_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_batches
    ADD CONSTRAINT fa_journal_batches_template_id_name_unique UNIQUE (template_id, name);


--
-- Name: fa_journal_lines fa_journal_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_lines
    ADD CONSTRAINT fa_journal_lines_pkey PRIMARY KEY (id);


--
-- Name: fa_journal_templates fa_journal_templates_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_templates
    ADD CONSTRAINT fa_journal_templates_name_unique UNIQUE (name);


--
-- Name: fa_journal_templates fa_journal_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_templates
    ADD CONSTRAINT fa_journal_templates_pkey PRIMARY KEY (id);


--
-- Name: fa_ledger_entries fa_ledger_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_ledger_entries
    ADD CONSTRAINT fa_ledger_entries_pkey PRIMARY KEY (id);


--
-- Name: fa_ledger_entries fa_ledger_unique_entry; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_ledger_entries
    ADD CONSTRAINT fa_ledger_unique_entry UNIQUE (fixed_asset_id, depreciation_book_id, entry_no);


--
-- Name: fa_locations fa_locations_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_locations
    ADD CONSTRAINT fa_locations_code_unique UNIQUE (code);


--
-- Name: fa_locations fa_locations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_locations
    ADD CONSTRAINT fa_locations_pkey PRIMARY KEY (id);


--
-- Name: fa_maintenance_logs fa_maintenance_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_maintenance_logs
    ADD CONSTRAINT fa_maintenance_logs_pkey PRIMARY KEY (id);


--
-- Name: fa_posting_groups fa_posting_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_code_unique UNIQUE (code);


--
-- Name: fa_posting_groups fa_posting_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_pkey PRIMARY KEY (id);


--
-- Name: fa_subclasses fa_subclasses_fa_class_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_subclasses
    ADD CONSTRAINT fa_subclasses_fa_class_id_code_unique UNIQUE (fa_class_id, code);


--
-- Name: fa_subclasses fa_subclasses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_subclasses
    ADD CONSTRAINT fa_subclasses_pkey PRIMARY KEY (id);


--
-- Name: factories factories_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.factories
    ADD CONSTRAINT factories_code_unique UNIQUE (code);


--
-- Name: factories factories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.factories
    ADD CONSTRAINT factories_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: fiscal_reopen_logs fiscal_reopen_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fiscal_reopen_logs
    ADD CONSTRAINT fiscal_reopen_logs_pkey PRIMARY KEY (id);


--
-- Name: asset_depreciation_ledger fixed_asset_depreciation_ledger_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_depreciation_ledger
    ADD CONSTRAINT fixed_asset_depreciation_ledger_pkey PRIMARY KEY (id);


--
-- Name: fixed_asset_journal_batches fixed_asset_journal_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_batches
    ADD CONSTRAINT fixed_asset_journal_batches_pkey PRIMARY KEY (id);


--
-- Name: fixed_asset_journal_batches fixed_asset_journal_batches_template_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_batches
    ADD CONSTRAINT fixed_asset_journal_batches_template_id_name_unique UNIQUE (template_id, name);


--
-- Name: fixed_asset_journal_lines fixed_asset_journal_lines_batch_id_line_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_lines
    ADD CONSTRAINT fixed_asset_journal_lines_batch_id_line_no_unique UNIQUE (batch_id, line_no);


--
-- Name: fixed_asset_journal_lines fixed_asset_journal_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_lines
    ADD CONSTRAINT fixed_asset_journal_lines_pkey PRIMARY KEY (id);


--
-- Name: fixed_asset_journal_templates fixed_asset_journal_templates_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_templates
    ADD CONSTRAINT fixed_asset_journal_templates_name_unique UNIQUE (name);


--
-- Name: fixed_asset_journal_templates fixed_asset_journal_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_templates
    ADD CONSTRAINT fixed_asset_journal_templates_pkey PRIMARY KEY (id);


--
-- Name: fixed_assets fixed_assets_fa_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_fa_no_unique UNIQUE (fa_no);


--
-- Name: fixed_assets fixed_assets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_pkey PRIMARY KEY (id);


--
-- Name: general_business_posting_groups general_business_posting_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_business_posting_groups
    ADD CONSTRAINT general_business_posting_groups_code_unique UNIQUE (code);


--
-- Name: general_business_posting_groups general_business_posting_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_business_posting_groups
    ADD CONSTRAINT general_business_posting_groups_pkey PRIMARY KEY (id);


--
-- Name: general_journal_batches general_journal_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_batches
    ADD CONSTRAINT general_journal_batches_pkey PRIMARY KEY (id);


--
-- Name: general_journal_batches general_journal_batches_template_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_batches
    ADD CONSTRAINT general_journal_batches_template_id_name_unique UNIQUE (template_id, name);


--
-- Name: general_journal_lines general_journal_lines_batch_id_line_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_lines
    ADD CONSTRAINT general_journal_lines_batch_id_line_no_unique UNIQUE (batch_id, line_no);


--
-- Name: general_journal_lines general_journal_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_lines
    ADD CONSTRAINT general_journal_lines_pkey PRIMARY KEY (id);


--
-- Name: general_journal_templates general_journal_templates_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_templates
    ADD CONSTRAINT general_journal_templates_name_unique UNIQUE (name);


--
-- Name: general_journal_templates general_journal_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_templates
    ADD CONSTRAINT general_journal_templates_pkey PRIMARY KEY (id);


--
-- Name: general_ledger_setup general_ledger_setup_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_ledger_setup
    ADD CONSTRAINT general_ledger_setup_pkey PRIMARY KEY (id);


--
-- Name: general_posting_setup_lines general_posting_setup_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setup_lines
    ADD CONSTRAINT general_posting_setup_lines_pkey PRIMARY KEY (id);


--
-- Name: general_posting_setups general_posting_setups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_pkey PRIMARY KEY (id);


--
-- Name: general_product_posting_groups general_product_posting_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_product_posting_groups
    ADD CONSTRAINT general_product_posting_groups_code_unique UNIQUE (code);


--
-- Name: general_product_posting_groups general_product_posting_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_product_posting_groups
    ADD CONSTRAINT general_product_posting_groups_pkey PRIMARY KEY (id);


--
-- Name: gl_accounts gl_accounts_account_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gl_accounts
    ADD CONSTRAINT gl_accounts_account_no_unique UNIQUE (account_no);


--
-- Name: gl_accounts gl_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gl_accounts
    ADD CONSTRAINT gl_accounts_pkey PRIMARY KEY (id);


--
-- Name: gl_entries gl_entries_entry_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gl_entries
    ADD CONSTRAINT gl_entries_entry_number_unique UNIQUE (entry_number);


--
-- Name: gl_entries gl_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gl_entries
    ADD CONSTRAINT gl_entries_pkey PRIMARY KEY (id);


--
-- Name: bin_contents idx_bin_inventory; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bin_contents
    ADD CONSTRAINT idx_bin_inventory UNIQUE (bin_id, item_id, lot_no, serial_no);


--
-- Name: inventory_adjustment_journals inventory_adjustment_journals_journal_batch_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustment_journals
    ADD CONSTRAINT inventory_adjustment_journals_journal_batch_name_unique UNIQUE (journal_batch_name);


--
-- Name: inventory_adjustment_journals inventory_adjustment_journals_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustment_journals
    ADD CONSTRAINT inventory_adjustment_journals_pkey PRIMARY KEY (id);


--
-- Name: inventory_adjustment_lines inventory_adjustment_lines_journal_id_line_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustment_lines
    ADD CONSTRAINT inventory_adjustment_lines_journal_id_line_no_unique UNIQUE (journal_id, line_no);


--
-- Name: inventory_adjustment_lines inventory_adjustment_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustment_lines
    ADD CONSTRAINT inventory_adjustment_lines_pkey PRIMARY KEY (id);


--
-- Name: inventory_posting_groups inventory_posting_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_posting_groups
    ADD CONSTRAINT inventory_posting_groups_code_unique UNIQUE (code);


--
-- Name: inventory_posting_groups inventory_posting_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_posting_groups
    ADD CONSTRAINT inventory_posting_groups_pkey PRIMARY KEY (id);


--
-- Name: inventory_posting_setups inventory_posting_setups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_posting_setups
    ADD CONSTRAINT inventory_posting_setups_pkey PRIMARY KEY (id);


--
-- Name: inventory_putaway_lines inventory_putaway_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_putaway_lines
    ADD CONSTRAINT inventory_putaway_lines_pkey PRIMARY KEY (id);


--
-- Name: inventory_putaways inventory_putaways_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_putaways
    ADD CONSTRAINT inventory_putaways_no_unique UNIQUE (no);


--
-- Name: inventory_putaways inventory_putaways_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_putaways
    ADD CONSTRAINT inventory_putaways_pkey PRIMARY KEY (id);


--
-- Name: item_category_assignments item_category_assignments_item_id_category_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_category_assignments
    ADD CONSTRAINT item_category_assignments_item_id_category_id_unique UNIQUE (item_id, category_id);


--
-- Name: item_category_assignments item_category_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_category_assignments
    ADD CONSTRAINT item_category_assignments_pkey PRIMARY KEY (assignment_id);


--
-- Name: item_charges item_charges_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_charges
    ADD CONSTRAINT item_charges_number_unique UNIQUE (number);


--
-- Name: item_charges item_charges_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_charges
    ADD CONSTRAINT item_charges_pkey PRIMARY KEY (id);


--
-- Name: item_journal_batches item_journal_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_batches
    ADD CONSTRAINT item_journal_batches_pkey PRIMARY KEY (id);


--
-- Name: item_journal_batches item_journal_batches_template_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_batches
    ADD CONSTRAINT item_journal_batches_template_id_name_unique UNIQUE (template_id, name);


--
-- Name: item_journal_lines item_journal_lines_batch_id_line_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_batch_id_line_no_unique UNIQUE (batch_id, line_no);


--
-- Name: item_journal_lines item_journal_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_pkey PRIMARY KEY (id);


--
-- Name: item_journal_templates item_journal_templates_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_templates
    ADD CONSTRAINT item_journal_templates_name_unique UNIQUE (name);


--
-- Name: item_journal_templates item_journal_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_templates
    ADD CONSTRAINT item_journal_templates_pkey PRIMARY KEY (id);


--
-- Name: item_ledger_entries item_ledger_entries_entry_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_ledger_entries
    ADD CONSTRAINT item_ledger_entries_entry_number_unique UNIQUE (entry_number);


--
-- Name: item_ledger_entries item_ledger_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_ledger_entries
    ADD CONSTRAINT item_ledger_entries_pkey PRIMARY KEY (id);


--
-- Name: item_lots item_lots_item_id_lot_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_lots
    ADD CONSTRAINT item_lots_item_id_lot_number_unique UNIQUE (item_id, lot_number);


--
-- Name: item_lots item_lots_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_lots
    ADD CONSTRAINT item_lots_pkey PRIMARY KEY (id);


--
-- Name: item_skus item_skus_barcode_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_skus
    ADD CONSTRAINT item_skus_barcode_unique UNIQUE (barcode);


--
-- Name: item_skus item_skus_item_id_location_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_skus
    ADD CONSTRAINT item_skus_item_id_location_id_unique UNIQUE (item_id, location_id);


--
-- Name: item_skus item_skus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_skus
    ADD CONSTRAINT item_skus_pkey PRIMARY KEY (id);


--
-- Name: item_skus item_skus_sku_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_skus
    ADD CONSTRAINT item_skus_sku_code_unique UNIQUE (sku_code);


--
-- Name: item_tracking_codes item_tracking_codes_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_tracking_codes
    ADD CONSTRAINT item_tracking_codes_code_unique UNIQUE (code);


--
-- Name: item_tracking_codes item_tracking_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_tracking_codes
    ADD CONSTRAINT item_tracking_codes_pkey PRIMARY KEY (id);


--
-- Name: item_tracking_lines item_tracking_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_tracking_lines
    ADD CONSTRAINT item_tracking_lines_pkey PRIMARY KEY (id);


--
-- Name: item_uom_assignments item_uom_assignments_item_id_uom_type_uom_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_uom_assignments
    ADD CONSTRAINT item_uom_assignments_item_id_uom_type_uom_id_unique UNIQUE (item_id, uom_type, uom_id);


--
-- Name: item_uom_assignments item_uom_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_uom_assignments
    ADD CONSTRAINT item_uom_assignments_pkey PRIMARY KEY (assignment_id);


--
-- Name: items items_item_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_item_code_unique UNIQUE (item_code);


--
-- Name: items items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: job_journal_lines job_journal_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_journal_lines
    ADD CONSTRAINT job_journal_lines_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: journal_batches journal_batches_journal_template_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_batches
    ADD CONSTRAINT journal_batches_journal_template_id_name_unique UNIQUE (journal_template_id, name);


--
-- Name: journal_batches journal_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_batches
    ADD CONSTRAINT journal_batches_pkey PRIMARY KEY (id);


--
-- Name: journal_lines journal_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_lines
    ADD CONSTRAINT journal_lines_pkey PRIMARY KEY (id);


--
-- Name: journal_posting_services journal_posting_services_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_posting_services
    ADD CONSTRAINT journal_posting_services_pkey PRIMARY KEY (id);


--
-- Name: journal_templates journal_templates_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_templates
    ADD CONSTRAINT journal_templates_name_unique UNIQUE (name);


--
-- Name: journal_templates journal_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_templates
    ADD CONSTRAINT journal_templates_pkey PRIMARY KEY (id);


--
-- Name: locations locations_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_code_unique UNIQUE (code);


--
-- Name: locations locations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_pkey PRIMARY KEY (id);


--
-- Name: machine_centers machine_centers_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.machine_centers
    ADD CONSTRAINT machine_centers_code_unique UNIQUE (code);


--
-- Name: machine_centers machine_centers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.machine_centers
    ADD CONSTRAINT machine_centers_pkey PRIMARY KEY (id);


--
-- Name: maintenance_contract_assets maintenance_contract_assets_maintenance_contract_id_fixed_asset; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_assets
    ADD CONSTRAINT maintenance_contract_assets_maintenance_contract_id_fixed_asset UNIQUE (maintenance_contract_id, fixed_asset_id);


--
-- Name: maintenance_contract_assets maintenance_contract_assets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_assets
    ADD CONSTRAINT maintenance_contract_assets_pkey PRIMARY KEY (id);


--
-- Name: maintenance_contract_billings maintenance_contract_billings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_billings
    ADD CONSTRAINT maintenance_contract_billings_pkey PRIMARY KEY (id);


--
-- Name: maintenance_contract_schedules maintenance_contract_schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_schedules
    ADD CONSTRAINT maintenance_contract_schedules_pkey PRIMARY KEY (id);


--
-- Name: maintenance_contracts maintenance_contracts_contract_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts
    ADD CONSTRAINT maintenance_contracts_contract_no_unique UNIQUE (contract_no);


--
-- Name: maintenance_contracts maintenance_contracts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts
    ADD CONSTRAINT maintenance_contracts_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: number_series number_series_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_series
    ADD CONSTRAINT number_series_code_unique UNIQUE (code);


--
-- Name: number_series_lines number_series_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_series_lines
    ADD CONSTRAINT number_series_lines_pkey PRIMARY KEY (id);


--
-- Name: number_series number_series_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_series
    ADD CONSTRAINT number_series_pkey PRIMARY KEY (id);


--
-- Name: overhead_cost_categories overhead_cost_categories_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.overhead_cost_categories
    ADD CONSTRAINT overhead_cost_categories_code_unique UNIQUE (code);


--
-- Name: overhead_cost_categories overhead_cost_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.overhead_cost_categories
    ADD CONSTRAINT overhead_cost_categories_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: pay_codes pay_codes_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pay_codes
    ADD CONSTRAINT pay_codes_code_unique UNIQUE (code);


--
-- Name: pay_codes pay_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pay_codes
    ADD CONSTRAINT pay_codes_pkey PRIMARY KEY (id);


--
-- Name: payment_applications payment_applications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_applications
    ADD CONSTRAINT payment_applications_pkey PRIMARY KEY (id);


--
-- Name: payment_journal_lines payment_journal_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_journal_lines
    ADD CONSTRAINT payment_journal_lines_pkey PRIMARY KEY (id);


--
-- Name: payment_terms payment_terms_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_terms
    ADD CONSTRAINT payment_terms_code_unique UNIQUE (code);


--
-- Name: payment_terms payment_terms_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_terms
    ADD CONSTRAINT payment_terms_pkey PRIMARY KEY (id);


--
-- Name: payments payments_payment_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_payment_number_unique UNIQUE (payment_number);


--
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);


--
-- Name: payroll_documents payroll_documents_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_documents
    ADD CONSTRAINT payroll_documents_document_number_unique UNIQUE (document_number);


--
-- Name: payroll_documents payroll_documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_documents
    ADD CONSTRAINT payroll_documents_pkey PRIMARY KEY (id);


--
-- Name: payroll_lines payroll_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_lines
    ADD CONSTRAINT payroll_lines_pkey PRIMARY KEY (id);


--
-- Name: payroll_periods payroll_periods_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_periods
    ADD CONSTRAINT payroll_periods_pkey PRIMARY KEY (id);


--
-- Name: payroll_posting_groups payroll_posting_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_posting_groups
    ADD CONSTRAINT payroll_posting_groups_code_unique UNIQUE (code);


--
-- Name: payroll_posting_groups payroll_posting_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_posting_groups
    ADD CONSTRAINT payroll_posting_groups_pkey PRIMARY KEY (id);


--
-- Name: payroll_statutory_setups payroll_statutory_setups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_statutory_setups
    ADD CONSTRAINT payroll_statutory_setups_code_unique UNIQUE (code);


--
-- Name: payroll_statutory_setups payroll_statutory_setups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_statutory_setups
    ADD CONSTRAINT payroll_statutory_setups_pkey PRIMARY KEY (id);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: physical_inventory_journals physical_inventory_journals_journal_batch_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.physical_inventory_journals
    ADD CONSTRAINT physical_inventory_journals_journal_batch_name_unique UNIQUE (journal_batch_name);


--
-- Name: physical_inventory_journals physical_inventory_journals_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.physical_inventory_journals
    ADD CONSTRAINT physical_inventory_journals_pkey PRIMARY KEY (id);


--
-- Name: physical_inventory_lines physical_inventory_lines_journal_id_line_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.physical_inventory_lines
    ADD CONSTRAINT physical_inventory_lines_journal_id_line_no_unique UNIQUE (journal_id, line_no);


--
-- Name: physical_inventory_lines physical_inventory_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.physical_inventory_lines
    ADD CONSTRAINT physical_inventory_lines_pkey PRIMARY KEY (id);


--
-- Name: posted_purchase_credit_memo_lines posted_purchase_credit_memo_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memo_lines
    ADD CONSTRAINT posted_purchase_credit_memo_lines_pkey PRIMARY KEY (id);


--
-- Name: posted_purchase_credit_memos posted_purchase_credit_memos_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memos
    ADD CONSTRAINT posted_purchase_credit_memos_document_number_unique UNIQUE (document_number);


--
-- Name: posted_purchase_credit_memos posted_purchase_credit_memos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memos
    ADD CONSTRAINT posted_purchase_credit_memos_pkey PRIMARY KEY (id);


--
-- Name: posted_purchase_invoice_lines posted_purchase_invoice_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoice_lines
    ADD CONSTRAINT posted_purchase_invoice_lines_pkey PRIMARY KEY (id);


--
-- Name: posted_purchase_invoices posted_purchase_invoices_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoices
    ADD CONSTRAINT posted_purchase_invoices_document_number_unique UNIQUE (document_number);


--
-- Name: posted_purchase_invoices posted_purchase_invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoices
    ADD CONSTRAINT posted_purchase_invoices_pkey PRIMARY KEY (id);


--
-- Name: posted_sales_credit_memo_lines posted_sales_credit_memo_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memo_lines
    ADD CONSTRAINT posted_sales_credit_memo_lines_pkey PRIMARY KEY (id);


--
-- Name: posted_sales_credit_memos posted_sales_credit_memos_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memos
    ADD CONSTRAINT posted_sales_credit_memos_document_number_unique UNIQUE (document_number);


--
-- Name: posted_sales_credit_memos posted_sales_credit_memos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memos
    ADD CONSTRAINT posted_sales_credit_memos_pkey PRIMARY KEY (id);


--
-- Name: posted_sales_invoice_lines posted_sales_invoice_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoice_lines
    ADD CONSTRAINT posted_sales_invoice_lines_pkey PRIMARY KEY (id);


--
-- Name: posted_sales_invoices posted_sales_invoices_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoices
    ADD CONSTRAINT posted_sales_invoices_document_number_unique UNIQUE (document_number);


--
-- Name: posted_sales_invoices posted_sales_invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoices
    ADD CONSTRAINT posted_sales_invoices_pkey PRIMARY KEY (id);


--
-- Name: price_change_template_lines price_change_template_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.price_change_template_lines
    ADD CONSTRAINT price_change_template_items_pkey PRIMARY KEY (id);


--
-- Name: price_change_templates price_change_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.price_change_templates
    ADD CONSTRAINT price_change_templates_pkey PRIMARY KEY (id);


--
-- Name: price_lists price_lists_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.price_lists
    ADD CONSTRAINT price_lists_pkey PRIMARY KEY (id);


--
-- Name: pricing_groups pricing_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_groups
    ADD CONSTRAINT pricing_groups_code_unique UNIQUE (code);


--
-- Name: pricing_groups pricing_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_groups
    ADD CONSTRAINT pricing_groups_pkey PRIMARY KEY (id);


--
-- Name: pricing_master pricing_master_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_master
    ADD CONSTRAINT pricing_master_pkey PRIMARY KEY (id);


--
-- Name: pricing_master_quantity_breaks pricing_master_quantity_breaks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_master_quantity_breaks
    ADD CONSTRAINT pricing_master_quantity_breaks_pkey PRIMARY KEY (id);


--
-- Name: pricing_master_quantity_breaks pricing_master_quantity_breaks_pricing_master_id_minimum_quanti; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_master_quantity_breaks
    ADD CONSTRAINT pricing_master_quantity_breaks_pricing_master_id_minimum_quanti UNIQUE (pricing_master_id, minimum_quantity);


--
-- Name: production_bom_lines production_bom_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_lines
    ADD CONSTRAINT production_bom_lines_pkey PRIMARY KEY (id);


--
-- Name: production_bom_version_lines production_bom_version_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_version_lines
    ADD CONSTRAINT production_bom_version_lines_pkey PRIMARY KEY (id);


--
-- Name: production_bom_versions production_bom_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_versions
    ADD CONSTRAINT production_bom_versions_pkey PRIMARY KEY (id);


--
-- Name: production_boms production_boms_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_boms
    ADD CONSTRAINT production_boms_code_unique UNIQUE (code);


--
-- Name: production_boms production_boms_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_boms
    ADD CONSTRAINT production_boms_pkey PRIMARY KEY (id);


--
-- Name: production_journal_batches production_journal_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_batches
    ADD CONSTRAINT production_journal_batches_pkey PRIMARY KEY (id);


--
-- Name: production_journal_batches production_journal_batches_template_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_batches
    ADD CONSTRAINT production_journal_batches_template_id_name_unique UNIQUE (template_id, name);


--
-- Name: production_journal_lines production_journal_lines_batch_id_line_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_batch_id_line_no_unique UNIQUE (batch_id, line_no);


--
-- Name: production_journal_lines production_journal_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_pkey PRIMARY KEY (id);


--
-- Name: production_journal_templates production_journal_templates_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_templates
    ADD CONSTRAINT production_journal_templates_name_unique UNIQUE (name);


--
-- Name: production_journal_templates production_journal_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_templates
    ADD CONSTRAINT production_journal_templates_pkey PRIMARY KEY (id);


--
-- Name: production_order_components production_order_components_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_components
    ADD CONSTRAINT production_order_components_pkey PRIMARY KEY (id);


--
-- Name: production_order_lines production_order_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_lines
    ADD CONSTRAINT production_order_lines_pkey PRIMARY KEY (id);


--
-- Name: production_order_lines production_order_lines_production_order_id_line_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_lines
    ADD CONSTRAINT production_order_lines_production_order_id_line_number_unique UNIQUE (production_order_id, line_number);


--
-- Name: production_order_routing_lines production_order_routing_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_routing_lines
    ADD CONSTRAINT production_order_routing_lines_pkey PRIMARY KEY (id);


--
-- Name: production_orders production_orders_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_document_number_unique UNIQUE (document_number);


--
-- Name: production_orders production_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_pkey PRIMARY KEY (id);


--
-- Name: purchase_credit_memo_lines purchase_credit_memo_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memo_lines
    ADD CONSTRAINT purchase_credit_memo_lines_pkey PRIMARY KEY (id);


--
-- Name: purchase_credit_memos purchase_credit_memos_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memos
    ADD CONSTRAINT purchase_credit_memos_document_number_unique UNIQUE (document_number);


--
-- Name: purchase_credit_memos purchase_credit_memos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memos
    ADD CONSTRAINT purchase_credit_memos_pkey PRIMARY KEY (id);


--
-- Name: purchase_invoice_lines purchase_invoice_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoice_lines
    ADD CONSTRAINT purchase_invoice_lines_pkey PRIMARY KEY (id);


--
-- Name: purchase_invoices purchase_invoices_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoices
    ADD CONSTRAINT purchase_invoices_document_number_unique UNIQUE (document_number);


--
-- Name: purchase_invoices purchase_invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoices
    ADD CONSTRAINT purchase_invoices_pkey PRIMARY KEY (id);


--
-- Name: purchase_order_lines purchase_order_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_lines
    ADD CONSTRAINT purchase_order_lines_pkey PRIMARY KEY (id);


--
-- Name: purchase_order_lines purchase_order_lines_purchase_order_id_line_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_lines
    ADD CONSTRAINT purchase_order_lines_purchase_order_id_line_number_unique UNIQUE (purchase_order_id, line_number);


--
-- Name: purchase_orders purchase_orders_order_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_order_number_unique UNIQUE (order_number);


--
-- Name: purchase_orders purchase_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_pkey PRIMARY KEY (id);


--
-- Name: purchase_prices purchase_prices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_prices
    ADD CONSTRAINT purchase_prices_pkey PRIMARY KEY (id);


--
-- Name: purchase_quote_approval_entries purchase_quote_approval_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_approval_entries
    ADD CONSTRAINT purchase_quote_approval_entries_pkey PRIMARY KEY (id);


--
-- Name: purchase_quote_archives purchase_quote_archives_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_archives
    ADD CONSTRAINT purchase_quote_archives_pkey PRIMARY KEY (id);


--
-- Name: purchase_quote_archives purchase_quote_archives_purchase_quote_id_version_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_archives
    ADD CONSTRAINT purchase_quote_archives_purchase_quote_id_version_no_unique UNIQUE (purchase_quote_id, version_no);


--
-- Name: purchase_quote_line_archives purchase_quote_line_archives_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_line_archives
    ADD CONSTRAINT purchase_quote_line_archives_pkey PRIMARY KEY (id);


--
-- Name: purchase_quote_line_archives purchase_quote_line_archives_purchase_quote_archive_id_line_no_; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_line_archives
    ADD CONSTRAINT purchase_quote_line_archives_purchase_quote_archive_id_line_no_ UNIQUE (purchase_quote_archive_id, line_no);


--
-- Name: purchase_quote_lines purchase_quote_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_lines
    ADD CONSTRAINT purchase_quote_lines_pkey PRIMARY KEY (id);


--
-- Name: purchase_quote_lines purchase_quote_lines_purchase_quote_id_line_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_lines
    ADD CONSTRAINT purchase_quote_lines_purchase_quote_id_line_no_unique UNIQUE (purchase_quote_id, line_no);


--
-- Name: purchase_quotes purchase_quotes_document_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quotes
    ADD CONSTRAINT purchase_quotes_document_no_unique UNIQUE (document_no);


--
-- Name: purchase_quotes purchase_quotes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quotes
    ADD CONSTRAINT purchase_quotes_pkey PRIMARY KEY (id);


--
-- Name: purchase_receipt_lines purchase_receipt_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipt_lines
    ADD CONSTRAINT purchase_receipt_lines_pkey PRIMARY KEY (id);


--
-- Name: purchase_receipt_lines purchase_receipt_lines_purchase_receipt_id_line_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipt_lines
    ADD CONSTRAINT purchase_receipt_lines_purchase_receipt_id_line_number_unique UNIQUE (purchase_receipt_id, line_number);


--
-- Name: purchase_receipts purchase_receipts_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipts
    ADD CONSTRAINT purchase_receipts_document_number_unique UNIQUE (document_number);


--
-- Name: purchase_receipts purchase_receipts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipts
    ADD CONSTRAINT purchase_receipts_pkey PRIMARY KEY (id);


--
-- Name: putaway_worksheet_lines putaway_worksheet_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.putaway_worksheet_lines
    ADD CONSTRAINT putaway_worksheet_lines_pkey PRIMARY KEY (id);


--
-- Name: putaway_worksheets putaway_worksheets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.putaway_worksheets
    ADD CONSTRAINT putaway_worksheets_pkey PRIMARY KEY (id);


--
-- Name: putaway_worksheets putaway_worksheets_worksheet_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.putaway_worksheets
    ADD CONSTRAINT putaway_worksheets_worksheet_number_unique UNIQUE (worksheet_number);


--
-- Name: reason_codes reason_codes_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reason_codes
    ADD CONSTRAINT reason_codes_code_unique UNIQUE (code);


--
-- Name: reason_codes reason_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reason_codes
    ADD CONSTRAINT reason_codes_pkey PRIMARY KEY (id);


--
-- Name: recurring_expenses recurring_expenses_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_expenses
    ADD CONSTRAINT recurring_expenses_code_unique UNIQUE (code);


--
-- Name: recurring_expenses recurring_expenses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_expenses
    ADD CONSTRAINT recurring_expenses_pkey PRIMARY KEY (id);


--
-- Name: recurring_journal_batches recurring_journal_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_batches
    ADD CONSTRAINT recurring_journal_batches_pkey PRIMARY KEY (id);


--
-- Name: recurring_journal_batches recurring_journal_batches_template_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_batches
    ADD CONSTRAINT recurring_journal_batches_template_id_name_unique UNIQUE (template_id, name);


--
-- Name: recurring_journal_lines recurring_journal_lines_batch_id_line_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_lines
    ADD CONSTRAINT recurring_journal_lines_batch_id_line_no_unique UNIQUE (batch_id, line_no);


--
-- Name: recurring_journal_lines recurring_journal_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_lines
    ADD CONSTRAINT recurring_journal_lines_pkey PRIMARY KEY (id);


--
-- Name: recurring_journal_templates recurring_journal_templates_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_templates
    ADD CONSTRAINT recurring_journal_templates_name_unique UNIQUE (name);


--
-- Name: recurring_journal_templates recurring_journal_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_templates
    ADD CONSTRAINT recurring_journal_templates_pkey PRIMARY KEY (id);


--
-- Name: reservation_entries reservation_entries_entry_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservation_entries
    ADD CONSTRAINT reservation_entries_entry_no_unique UNIQUE (entry_no);


--
-- Name: reservation_entries reservation_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservation_entries
    ADD CONSTRAINT reservation_entries_pkey PRIMARY KEY (id);


--
-- Name: resource_journal_lines resource_journal_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resource_journal_lines
    ADD CONSTRAINT resource_journal_lines_pkey PRIMARY KEY (id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: routing_lines routing_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_lines
    ADD CONSTRAINT routing_lines_pkey PRIMARY KEY (id);


--
-- Name: routing_version_lines routing_version_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_version_lines
    ADD CONSTRAINT routing_version_lines_pkey PRIMARY KEY (id);


--
-- Name: routing_versions routing_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_versions
    ADD CONSTRAINT routing_versions_pkey PRIMARY KEY (id);


--
-- Name: routing_versions routing_versions_routing_id_version_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_versions
    ADD CONSTRAINT routing_versions_routing_id_version_code_unique UNIQUE (routing_id, version_code);


--
-- Name: routings routings_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routings
    ADD CONSTRAINT routings_code_unique UNIQUE (code);


--
-- Name: routings routings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routings
    ADD CONSTRAINT routings_pkey PRIMARY KEY (id);


--
-- Name: sales_credit_memo_lines sales_credit_memo_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_credit_memo_lines
    ADD CONSTRAINT sales_credit_memo_lines_pkey PRIMARY KEY (id);


--
-- Name: sales_credit_memos sales_credit_memos_memo_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_credit_memos
    ADD CONSTRAINT sales_credit_memos_memo_number_unique UNIQUE (memo_number);


--
-- Name: sales_credit_memos sales_credit_memos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_credit_memos
    ADD CONSTRAINT sales_credit_memos_pkey PRIMARY KEY (id);


--
-- Name: sales_invoice_lines sales_invoice_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_invoice_lines
    ADD CONSTRAINT sales_invoice_lines_pkey PRIMARY KEY (id);


--
-- Name: sales_invoices sales_invoices_invoice_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_invoices
    ADD CONSTRAINT sales_invoices_invoice_number_unique UNIQUE (invoice_number);


--
-- Name: sales_invoices sales_invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_invoices
    ADD CONSTRAINT sales_invoices_pkey PRIMARY KEY (id);


--
-- Name: sales_order_lines sales_order_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_order_lines
    ADD CONSTRAINT sales_order_lines_pkey PRIMARY KEY (id);


--
-- Name: sales_order_lines sales_order_lines_sales_order_id_line_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_order_lines
    ADD CONSTRAINT sales_order_lines_sales_order_id_line_number_unique UNIQUE (sales_order_id, line_number);


--
-- Name: sales_orders sales_orders_order_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_order_number_unique UNIQUE (order_number);


--
-- Name: sales_orders sales_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_pkey PRIMARY KEY (id);


--
-- Name: sales_quote_items sales_quote_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_quote_items
    ADD CONSTRAINT sales_quote_items_pkey PRIMARY KEY (id);


--
-- Name: sales_quote_revisions sales_quote_revisions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_quote_revisions
    ADD CONSTRAINT sales_quote_revisions_pkey PRIMARY KEY (id);


--
-- Name: sales_quotes sales_quotes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_quotes
    ADD CONSTRAINT sales_quotes_pkey PRIMARY KEY (id);


--
-- Name: sales_quotes sales_quotes_quote_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_quotes
    ADD CONSTRAINT sales_quotes_quote_no_unique UNIQUE (quote_no);


--
-- Name: sales_shipment_headers sales_shipment_headers_document_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_shipment_headers
    ADD CONSTRAINT sales_shipment_headers_document_no_unique UNIQUE (document_no);


--
-- Name: sales_shipment_headers sales_shipment_headers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_shipment_headers
    ADD CONSTRAINT sales_shipment_headers_pkey PRIMARY KEY (id);


--
-- Name: sales_shipment_lines sales_shipment_lines_document_no_line_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_shipment_lines
    ADD CONSTRAINT sales_shipment_lines_document_no_line_no_unique UNIQUE (document_no, line_no);


--
-- Name: sales_shipment_lines sales_shipment_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_shipment_lines
    ADD CONSTRAINT sales_shipment_lines_pkey PRIMARY KEY (id);


--
-- Name: salesperson_purchasers salesperson_purchasers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salesperson_purchasers
    ADD CONSTRAINT salesperson_purchasers_pkey PRIMARY KEY (code);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: shipment_methods shipment_methods_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipment_methods
    ADD CONSTRAINT shipment_methods_code_unique UNIQUE (code);


--
-- Name: shipment_methods shipment_methods_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipment_methods
    ADD CONSTRAINT shipment_methods_pkey PRIMARY KEY (id);


--
-- Name: shipping_agent_services shipping_agent_services_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_agent_services
    ADD CONSTRAINT shipping_agent_services_pkey PRIMARY KEY (id);


--
-- Name: shipping_agents shipping_agents_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_agents
    ADD CONSTRAINT shipping_agents_code_unique UNIQUE (code);


--
-- Name: shipping_agents shipping_agents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipping_agents
    ADD CONSTRAINT shipping_agents_pkey PRIMARY KEY (id);


--
-- Name: social_security_tiers social_security_tiers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_security_tiers
    ADD CONSTRAINT social_security_tiers_pkey PRIMARY KEY (id);


--
-- Name: sync_logs sync_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sync_logs
    ADD CONSTRAINT sync_logs_pkey PRIMARY KEY (id);


--
-- Name: tax_brackets tax_brackets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_brackets
    ADD CONSTRAINT tax_brackets_pkey PRIMARY KEY (id);


--
-- Name: tax_tables tax_tables_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_tables
    ADD CONSTRAINT tax_tables_pkey PRIMARY KEY (id);


--
-- Name: inventory_posting_setups unique_inventory_setup; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_posting_setups
    ADD CONSTRAINT unique_inventory_setup UNIQUE (location_id, inventory_posting_group_id);


--
-- Name: general_posting_setups unique_posting_setup; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT unique_posting_setup UNIQUE (general_business_posting_group_id, general_product_posting_group_id);


--
-- Name: general_posting_setup_lines unique_setup_line_type; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setup_lines
    ADD CONSTRAINT unique_setup_line_type UNIQUE (general_posting_setup_id, line_type);


--
-- Name: unit_of_measures unit_of_measures_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unit_of_measures
    ADD CONSTRAINT unit_of_measures_pkey PRIMARY KEY (id);


--
-- Name: unit_of_measures unit_of_measures_uom_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unit_of_measures
    ADD CONSTRAINT unit_of_measures_uom_code_unique UNIQUE (uom_code);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_employee_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_employee_id_unique UNIQUE (employee_id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: value_entries value_entries_entry_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.value_entries
    ADD CONSTRAINT value_entries_entry_no_unique UNIQUE (entry_no);


--
-- Name: value_entries value_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.value_entries
    ADD CONSTRAINT value_entries_pkey PRIMARY KEY (id);


--
-- Name: vat_business_posting_groups vat_business_posting_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_business_posting_groups
    ADD CONSTRAINT vat_business_posting_groups_code_unique UNIQUE (code);


--
-- Name: vat_business_posting_groups vat_business_posting_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_business_posting_groups
    ADD CONSTRAINT vat_business_posting_groups_pkey PRIMARY KEY (id);


--
-- Name: vat_masters vat_masters_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_masters
    ADD CONSTRAINT vat_masters_code_unique UNIQUE (code);


--
-- Name: vat_masters vat_masters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_masters
    ADD CONSTRAINT vat_masters_pkey PRIMARY KEY (id);


--
-- Name: vat_posting_setups vat_posting_setup_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_posting_setups
    ADD CONSTRAINT vat_posting_setup_unique UNIQUE (vat_business_posting_group_id, vat_product_posting_group_id);


--
-- Name: vat_posting_setups vat_posting_setups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_posting_setups
    ADD CONSTRAINT vat_posting_setups_pkey PRIMARY KEY (id);


--
-- Name: vat_product_posting_groups vat_product_posting_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_product_posting_groups
    ADD CONSTRAINT vat_product_posting_groups_code_unique UNIQUE (code);


--
-- Name: vat_product_posting_groups vat_product_posting_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_product_posting_groups
    ADD CONSTRAINT vat_product_posting_groups_pkey PRIMARY KEY (id);


--
-- Name: vendor_invoice_lines vendor_invoice_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoice_lines
    ADD CONSTRAINT vendor_invoice_lines_pkey PRIMARY KEY (id);


--
-- Name: vendor_invoice_lines vendor_invoice_lines_vendor_invoice_id_line_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoice_lines
    ADD CONSTRAINT vendor_invoice_lines_vendor_invoice_id_line_number_unique UNIQUE (vendor_invoice_id, line_number);


--
-- Name: vendor_invoices vendor_invoices_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoices
    ADD CONSTRAINT vendor_invoices_document_number_unique UNIQUE (document_number);


--
-- Name: vendor_invoices vendor_invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoices
    ADD CONSTRAINT vendor_invoices_pkey PRIMARY KEY (id);


--
-- Name: vendor_items vendor_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_items
    ADD CONSTRAINT vendor_items_pkey PRIMARY KEY (id);


--
-- Name: vendor_items vendor_items_vendor_id_item_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_items
    ADD CONSTRAINT vendor_items_vendor_id_item_id_unique UNIQUE (vendor_id, item_id);


--
-- Name: vendor_ledger_entries vendor_ledger_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_ledger_entries
    ADD CONSTRAINT vendor_ledger_entries_pkey PRIMARY KEY (id);


--
-- Name: vendor_ledger_entries vendor_ledger_entries_vendor_id_entry_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_ledger_entries
    ADD CONSTRAINT vendor_ledger_entries_vendor_id_entry_number_unique UNIQUE (vendor_id, entry_number);


--
-- Name: vendor_posting_groups vendor_posting_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_posting_groups
    ADD CONSTRAINT vendor_posting_groups_code_unique UNIQUE (code);


--
-- Name: vendor_posting_groups vendor_posting_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_posting_groups
    ADD CONSTRAINT vendor_posting_groups_pkey PRIMARY KEY (id);


--
-- Name: vendors vendors_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_pkey PRIMARY KEY (id);


--
-- Name: vendors vendors_vendor_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_vendor_code_unique UNIQUE (vendor_code);


--
-- Name: warehouse_activities warehouse_activities_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activities
    ADD CONSTRAINT warehouse_activities_no_unique UNIQUE (no);


--
-- Name: warehouse_activities warehouse_activities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activities
    ADD CONSTRAINT warehouse_activities_pkey PRIMARY KEY (id);


--
-- Name: warehouse_activity_lines warehouse_activity_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activity_lines
    ADD CONSTRAINT warehouse_activity_lines_pkey PRIMARY KEY (id);


--
-- Name: warehouse_activity_lines warehouse_activity_lines_warehouse_activity_id_line_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activity_lines
    ADD CONSTRAINT warehouse_activity_lines_warehouse_activity_id_line_no_unique UNIQUE (warehouse_activity_id, line_no);


--
-- Name: warehouse_entries warehouse_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_entries
    ADD CONSTRAINT warehouse_entries_pkey PRIMARY KEY (id);


--
-- Name: warehouse_journal_batches warehouse_journal_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_batches
    ADD CONSTRAINT warehouse_journal_batches_pkey PRIMARY KEY (id);


--
-- Name: warehouse_journal_batches warehouse_journal_batches_template_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_batches
    ADD CONSTRAINT warehouse_journal_batches_template_id_name_unique UNIQUE (template_id, name);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_batch_id_line_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_batch_id_line_no_unique UNIQUE (batch_id, line_no);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_pkey PRIMARY KEY (id);


--
-- Name: warehouse_journal_templates warehouse_journal_templates_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_templates
    ADD CONSTRAINT warehouse_journal_templates_name_unique UNIQUE (name);


--
-- Name: warehouse_journal_templates warehouse_journal_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_templates
    ADD CONSTRAINT warehouse_journal_templates_pkey PRIMARY KEY (id);


--
-- Name: warehouse_pick_lines warehouse_pick_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_pick_lines
    ADD CONSTRAINT warehouse_pick_lines_pkey PRIMARY KEY (id);


--
-- Name: warehouse_pick_lines warehouse_pick_lines_warehouse_pick_id_line_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_pick_lines
    ADD CONSTRAINT warehouse_pick_lines_warehouse_pick_id_line_no_unique UNIQUE (warehouse_pick_id, line_no);


--
-- Name: warehouse_picks warehouse_picks_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_picks
    ADD CONSTRAINT warehouse_picks_no_unique UNIQUE (no);


--
-- Name: warehouse_picks warehouse_picks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_picks
    ADD CONSTRAINT warehouse_picks_pkey PRIMARY KEY (id);


--
-- Name: warehouse_putaway_lines warehouse_putaway_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_putaway_lines
    ADD CONSTRAINT warehouse_putaway_lines_pkey PRIMARY KEY (id);


--
-- Name: warehouse_putaways warehouse_putaways_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_putaways
    ADD CONSTRAINT warehouse_putaways_no_unique UNIQUE (no);


--
-- Name: warehouse_putaways warehouse_putaways_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_putaways
    ADD CONSTRAINT warehouse_putaways_pkey PRIMARY KEY (id);


--
-- Name: warehouse_receipt_lines warehouse_receipt_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_receipt_lines
    ADD CONSTRAINT warehouse_receipt_lines_pkey PRIMARY KEY (id);


--
-- Name: warehouse_receipts warehouse_receipts_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_receipts
    ADD CONSTRAINT warehouse_receipts_document_number_unique UNIQUE (document_number);


--
-- Name: warehouse_receipts warehouse_receipts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_receipts
    ADD CONSTRAINT warehouse_receipts_pkey PRIMARY KEY (id);


--
-- Name: warehouse_requests warehouse_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_requests
    ADD CONSTRAINT warehouse_requests_pkey PRIMARY KEY (id);


--
-- Name: warehouse_setup warehouse_setup_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_setup
    ADD CONSTRAINT warehouse_setup_pkey PRIMARY KEY (id);


--
-- Name: warehouse_shipment_lines warehouse_shipment_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_shipment_lines
    ADD CONSTRAINT warehouse_shipment_lines_pkey PRIMARY KEY (id);


--
-- Name: warehouse_shipments warehouse_shipments_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_shipments
    ADD CONSTRAINT warehouse_shipments_document_number_unique UNIQUE (document_number);


--
-- Name: warehouse_shipments warehouse_shipments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_shipments
    ADD CONSTRAINT warehouse_shipments_pkey PRIMARY KEY (id);


--
-- Name: work_center_bins work_center_bins_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_bins
    ADD CONSTRAINT work_center_bins_pkey PRIMARY KEY (id);


--
-- Name: work_center_calendars work_center_calendars_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_calendars
    ADD CONSTRAINT work_center_calendars_pkey PRIMARY KEY (id);


--
-- Name: work_center_calendars work_center_calendars_work_center_id_date_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_calendars
    ADD CONSTRAINT work_center_calendars_work_center_id_date_unique UNIQUE (work_center_id, date);


--
-- Name: work_center_groups work_center_groups_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_groups
    ADD CONSTRAINT work_center_groups_code_unique UNIQUE (code);


--
-- Name: work_center_groups work_center_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_groups
    ADD CONSTRAINT work_center_groups_pkey PRIMARY KEY (id);


--
-- Name: work_centers work_centers_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_centers
    ADD CONSTRAINT work_centers_code_unique UNIQUE (code);


--
-- Name: work_centers work_centers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_centers
    ADD CONSTRAINT work_centers_pkey PRIMARY KEY (id);


--
-- Name: zones zones_location_id_zone_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.zones
    ADD CONSTRAINT zones_location_id_zone_code_unique UNIQUE (location_id, zone_code);


--
-- Name: zones zones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.zones
    ADD CONSTRAINT zones_pkey PRIMARY KEY (id);


--
-- Name: account_schedule_lines_schedule_id_line_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_schedule_lines_schedule_id_line_no_index ON public.account_schedule_lines USING btree (schedule_id, line_no);


--
-- Name: accounting_periods_is_closed_start_date_end_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX accounting_periods_is_closed_start_date_end_date_index ON public.accounting_periods USING btree (is_closed, start_date, end_date);


--
-- Name: actual_overhead_costs_fiscal_year_period_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX actual_overhead_costs_fiscal_year_period_no_index ON public.actual_overhead_costs USING btree (fiscal_year, period_no);


--
-- Name: actual_overhead_costs_location_id_period_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX actual_overhead_costs_location_id_period_index ON public.actual_overhead_costs USING btree (location_id, period);


--
-- Name: actual_overhead_costs_status_period_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX actual_overhead_costs_status_period_index ON public.actual_overhead_costs USING btree (status, period);


--
-- Name: actual_overhead_costs_work_center_id_period_cost_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX actual_overhead_costs_work_center_id_period_cost_type_index ON public.actual_overhead_costs USING btree (work_center_id, period, cost_type);


--
-- Name: approvable_sequence_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approvable_sequence_index ON public.approval_entries USING btree (approvable_type, approvable_id, sequence_no);


--
-- Name: approval_entries_approvable_type_approvable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_entries_approvable_type_approvable_id_index ON public.approval_entries USING btree (approvable_type, approvable_id);


--
-- Name: approval_entries_approver_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_entries_approver_id_index ON public.approval_entries USING btree (approver_id);


--
-- Name: approval_entries_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX approval_entries_status_index ON public.approval_entries USING btree (status);


--
-- Name: attendance_ledger_entries_attendance_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX attendance_ledger_entries_attendance_date_index ON public.attendance_ledger_entries USING btree (attendance_date);


--
-- Name: attendance_ledger_entries_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX attendance_ledger_entries_status_index ON public.attendance_ledger_entries USING btree (status);


--
-- Name: bank_account_ledger_entries_bank_account_id_open_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bank_account_ledger_entries_bank_account_id_open_index ON public.bank_account_ledger_entries USING btree (bank_account_id, open);


--
-- Name: bank_account_ledger_entries_bank_account_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bank_account_ledger_entries_bank_account_id_posting_date_index ON public.bank_account_ledger_entries USING btree (bank_account_id, posting_date);


--
-- Name: bank_account_ledger_entries_bank_account_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bank_account_ledger_entries_bank_account_id_status_index ON public.bank_account_ledger_entries USING btree (bank_account_id, status);


--
-- Name: bank_account_ledger_entries_check_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bank_account_ledger_entries_check_no_index ON public.bank_account_ledger_entries USING btree (check_no);


--
-- Name: bank_account_ledger_entries_entry_type_document_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bank_account_ledger_entries_entry_type_document_no_index ON public.bank_account_ledger_entries USING btree (entry_type, document_no);


--
-- Name: bank_account_ledger_entries_posting_date_document_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bank_account_ledger_entries_posting_date_document_no_index ON public.bank_account_ledger_entries USING btree (posting_date, document_no);


--
-- Name: bank_account_ledger_entries_source_type_source_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bank_account_ledger_entries_source_type_source_id_index ON public.bank_account_ledger_entries USING btree (source_type, source_id);


--
-- Name: bank_account_ledger_entries_statement_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bank_account_ledger_entries_statement_no_index ON public.bank_account_ledger_entries USING btree (statement_no);


--
-- Name: bank_account_statement_lines_bank_account_id_statement_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bank_account_statement_lines_bank_account_id_statement_no_index ON public.bank_account_statement_lines USING btree (bank_account_id, statement_no);


--
-- Name: bin_contents_item_id_zone_id_bin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bin_contents_item_id_zone_id_bin_id_index ON public.bin_contents USING btree (item_id, zone_id, bin_id);


--
-- Name: blanket_order_lines_blanket_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX blanket_order_lines_blanket_order_id_index ON public.blanket_order_lines USING btree (blanket_order_id);


--
-- Name: blanket_order_lines_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX blanket_order_lines_no_index ON public.blanket_order_lines USING btree (no);


--
-- Name: blanket_order_lines_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX blanket_order_lines_type_index ON public.blanket_order_lines USING btree (type);


--
-- Name: blanket_orders_document_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX blanket_orders_document_number_index ON public.blanket_orders USING btree (document_number);


--
-- Name: blanket_orders_released_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX blanket_orders_released_index ON public.blanket_orders USING btree (released);


--
-- Name: blanket_orders_starting_date_ending_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX blanket_orders_starting_date_ending_date_index ON public.blanket_orders USING btree (starting_date, ending_date);


--
-- Name: blanket_orders_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX blanket_orders_status_index ON public.blanket_orders USING btree (status);


--
-- Name: blanket_orders_vendor_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX blanket_orders_vendor_id_index ON public.blanket_orders USING btree (vendor_id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: capacity_ledger_entries_document_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX capacity_ledger_entries_document_number_index ON public.capacity_ledger_entries USING btree (document_number);


--
-- Name: capacity_ledger_entries_production_order_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX capacity_ledger_entries_production_order_id_posting_date_index ON public.capacity_ledger_entries USING btree (production_order_id, posting_date);


--
-- Name: capacity_ledger_entries_work_center_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX capacity_ledger_entries_work_center_id_posting_date_index ON public.capacity_ledger_entries USING btree (work_center_id, posting_date);


--
-- Name: capex_project_lines_capex_project_id_line_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX capex_project_lines_capex_project_id_line_type_index ON public.capex_project_lines USING btree (capex_project_id, line_type);


--
-- Name: capex_project_lines_capex_project_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX capex_project_lines_capex_project_id_status_index ON public.capex_project_lines USING btree (capex_project_id, status);


--
-- Name: capex_project_lines_eligible_for_capitalization_capitalized_ind; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX capex_project_lines_eligible_for_capitalization_capitalized_ind ON public.capex_project_lines USING btree (eligible_for_capitalization, capitalized);


--
-- Name: capex_project_lines_production_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX capex_project_lines_production_order_id_index ON public.capex_project_lines USING btree (production_order_id);


--
-- Name: capex_project_lines_source_document_type_source_document_id_ind; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX capex_project_lines_source_document_type_source_document_id_ind ON public.capex_project_lines USING btree (source_document_type, source_document_id);


--
-- Name: capex_projects_project_manager_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX capex_projects_project_manager_id_status_index ON public.capex_projects USING btree (project_manager_id, status);


--
-- Name: capex_projects_status_planned_end_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX capex_projects_status_planned_end_date_index ON public.capex_projects USING btree (status, planned_end_date);


--
-- Name: categories_category_type_level_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX categories_category_type_level_is_active_index ON public.categories USING btree (category_type, level, is_active);


--
-- Name: categories_hierarchy_path_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX categories_hierarchy_path_index ON public.categories USING btree (hierarchy_path);


--
-- Name: categories_parent_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX categories_parent_id_is_active_index ON public.categories USING btree (parent_id, is_active);


--
-- Name: categories_sort_order_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX categories_sort_order_is_active_index ON public.categories USING btree (sort_order, is_active);


--
-- Name: chart_of_accounts_account_category_income_balance_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chart_of_accounts_account_category_income_balance_index ON public.chart_of_accounts USING btree (account_category, income_balance);


--
-- Name: chart_of_accounts_structural_type_account_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chart_of_accounts_structural_type_account_number_index ON public.chart_of_accounts USING btree (structural_type, account_number);


--
-- Name: contacts_company_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contacts_company_name_index ON public.contacts USING btree (company_name);


--
-- Name: contacts_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contacts_email_index ON public.contacts USING btree (email);


--
-- Name: contacts_role_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contacts_role_index ON public.contacts USING btree (role);


--
-- Name: contacts_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contacts_type_index ON public.contacts USING btree (type);


--
-- Name: currencies_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX currencies_is_active_index ON public.currencies USING btree (is_active);


--
-- Name: currencies_is_lcy_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX currencies_is_lcy_index ON public.currencies USING btree (is_lcy);


--
-- Name: currency_adjustment_ledger_adjustment_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX currency_adjustment_ledger_adjustment_type_index ON public.currency_adjustment_ledger USING btree (adjustment_type);


--
-- Name: currency_adjustment_ledger_currency_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX currency_adjustment_ledger_currency_id_posting_date_index ON public.currency_adjustment_ledger USING btree (currency_id, posting_date);


--
-- Name: currency_adjustment_ledger_document_type_document_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX currency_adjustment_ledger_document_type_document_no_index ON public.currency_adjustment_ledger USING btree (document_type, document_no);


--
-- Name: currency_buffers_currency_id_buffer_type_adjusted_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX currency_buffers_currency_id_buffer_type_adjusted_index ON public.currency_buffers USING btree (currency_id, buffer_type, adjusted);


--
-- Name: currency_exchange_rates_currency_id_is_current_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX currency_exchange_rates_currency_id_is_current_index ON public.currency_exchange_rates USING btree (currency_id, is_current);


--
-- Name: currency_exchange_rates_starting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX currency_exchange_rates_starting_date_index ON public.currency_exchange_rates USING btree (starting_date);


--
-- Name: customer_ledger_entries_customer_id_open_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX customer_ledger_entries_customer_id_open_index ON public.customer_ledger_entries USING btree (customer_id, open);


--
-- Name: customer_ledger_entries_customer_id_posting_date_entry_number_i; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX customer_ledger_entries_customer_id_posting_date_entry_number_i ON public.customer_ledger_entries USING btree (customer_id, posting_date, entry_number);


--
-- Name: customer_ledger_entries_document_type_document_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX customer_ledger_entries_document_type_document_number_index ON public.customer_ledger_entries USING btree (document_type, document_number);


--
-- Name: customer_ledger_entries_open_due_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX customer_ledger_entries_open_due_date_index ON public.customer_ledger_entries USING btree (open, due_date);


--
-- Name: default_dimensions_table_id_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX default_dimensions_table_id_no_index ON public.default_dimensions USING btree (table_id, no);


--
-- Name: department_employee_employee_id_end_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX department_employee_employee_id_end_date_index ON public.department_employee USING btree (employee_id, end_date);


--
-- Name: departments_global_dimension_1_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX departments_global_dimension_1_code_index ON public.departments USING btree (global_dimension_1_code);


--
-- Name: departments_manager_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX departments_manager_id_index ON public.departments USING btree (manager_id);


--
-- Name: departments_parent_department_id_level_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX departments_parent_department_id_level_index ON public.departments USING btree (parent_department_id, level);


--
-- Name: departments_status_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX departments_status_type_index ON public.departments USING btree (status, type);


--
-- Name: dim_set_tree_parent_value; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dim_set_tree_parent_value ON public.dimension_set_tree_nodes USING btree (parent_dimension_set_id, dimension_value_id);


--
-- Name: dim_set_tree_set_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dim_set_tree_set_id ON public.dimension_set_tree_nodes USING btree (dimension_set_id);


--
-- Name: dimension_combinations_dimension_1_code_dimension_2_code_combin; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dimension_combinations_dimension_1_code_dimension_2_code_combin ON public.dimension_combinations USING btree (dimension_1_code, dimension_2_code, combination_type);


--
-- Name: dimension_set_entries_dimension_code_dimension_value_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dimension_set_entries_dimension_code_dimension_value_code_index ON public.dimension_set_entries USING btree (dimension_code, dimension_value_code);


--
-- Name: dimension_values_dimension_id_dimension_value_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dimension_values_dimension_id_dimension_value_type_index ON public.dimension_values USING btree (dimension_id, dimension_value_type);


--
-- Name: dimensions_dimension_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dimensions_dimension_type_index ON public.dimensions USING btree (dimension_type);


--
-- Name: dimensions_global_dimension_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dimensions_global_dimension_no_index ON public.dimensions USING btree (global_dimension_no);


--
-- Name: document_headers_doc_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_headers_doc_date_index ON public.document_headers USING btree (doc_date);


--
-- Name: document_headers_doc_no_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_headers_doc_no_posting_date_index ON public.document_headers USING btree (doc_no, posting_date);


--
-- Name: document_headers_doc_type_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_headers_doc_type_status_index ON public.document_headers USING btree (doc_type, status);


--
-- Name: employee_promotion_histories_effective_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employee_promotion_histories_effective_date_index ON public.employee_promotion_histories USING btree (effective_date);


--
-- Name: employee_promotion_histories_employee_id_effective_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employee_promotion_histories_employee_id_effective_date_index ON public.employee_promotion_histories USING btree (employee_id, effective_date);


--
-- Name: employees_assignment_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employees_assignment_type_index ON public.employees USING btree (assignment_type);


--
-- Name: employees_business_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employees_business_code_index ON public.employees USING btree (business_code);


--
-- Name: employees_department_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employees_department_code_index ON public.employees USING btree (department_code);


--
-- Name: employees_factory_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employees_factory_code_index ON public.employees USING btree (factory_code);


--
-- Name: expense_categories_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expense_categories_is_active_index ON public.expense_categories USING btree (is_active);


--
-- Name: expense_transactions_category_code_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expense_transactions_category_code_posting_date_index ON public.expense_transactions USING btree (category_code, posting_date);


--
-- Name: expense_transactions_customer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expense_transactions_customer_id_index ON public.expense_transactions USING btree (customer_id);


--
-- Name: expense_transactions_document_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expense_transactions_document_no_index ON public.expense_transactions USING btree (document_no);


--
-- Name: expense_transactions_posting_date_account_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expense_transactions_posting_date_account_type_index ON public.expense_transactions USING btree (posting_date, account_type);


--
-- Name: expense_transactions_product_category_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expense_transactions_product_category_id_posting_date_index ON public.expense_transactions USING btree (category_id, posting_date);


--
-- Name: expense_transactions_vendor_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expense_transactions_vendor_id_index ON public.expense_transactions USING btree (vendor_id);


--
-- Name: fa_journal_lines_batch_id_line_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fa_journal_lines_batch_id_line_no_index ON public.fa_journal_lines USING btree (batch_id, line_no);


--
-- Name: fa_journal_lines_fixed_asset_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fa_journal_lines_fixed_asset_id_posting_date_index ON public.fa_journal_lines USING btree (fixed_asset_id, posting_date);


--
-- Name: fa_ledger_entries_document_type_document_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fa_ledger_entries_document_type_document_no_index ON public.fa_ledger_entries USING btree (document_type, document_no);


--
-- Name: fa_ledger_entries_fa_posting_type_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fa_ledger_entries_fa_posting_type_posting_date_index ON public.fa_ledger_entries USING btree (fa_posting_type, posting_date);


--
-- Name: fa_ledger_entries_fixed_asset_id_depreciation_book_id_posting_d; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fa_ledger_entries_fixed_asset_id_depreciation_book_id_posting_d ON public.fa_ledger_entries USING btree (fixed_asset_id, depreciation_book_id, posting_date);


--
-- Name: fa_ledger_entries_reversed_entry_fixed_asset_id_reversed_entry_; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fa_ledger_entries_reversed_entry_fixed_asset_id_reversed_entry_ ON public.fa_ledger_entries USING btree (reversed_entry_fixed_asset_id, reversed_entry_depreciation_book_id, reversed_entry_no);


--
-- Name: fixed_asset_depreciation_ledger_fixed_asset_id_depreciation_per; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fixed_asset_depreciation_ledger_fixed_asset_id_depreciation_per ON public.asset_depreciation_ledger USING btree (asset_id, depreciation_period);


--
-- Name: fixed_assets_acquisition_date_depreciation_starting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fixed_assets_acquisition_date_depreciation_starting_date_index ON public.fixed_assets USING btree (acquisition_date, depreciation_starting_date);


--
-- Name: fixed_assets_fa_posting_group_id_depreciation_book_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fixed_assets_fa_posting_group_id_depreciation_book_id_index ON public.fixed_assets USING btree (fa_posting_group_id, depreciation_book_id);


--
-- Name: fixed_assets_fa_type_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fixed_assets_fa_type_status_index ON public.fixed_assets USING btree (fa_type, status);


--
-- Name: general_journal_lines_posting_date_account_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX general_journal_lines_posting_date_account_id_index ON public.general_journal_lines USING btree (posting_date, account_id);


--
-- Name: gl_accounts_account_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gl_accounts_account_category_index ON public.gl_accounts USING btree (account_category);


--
-- Name: gl_accounts_account_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gl_accounts_account_no_index ON public.gl_accounts USING btree (account_no);


--
-- Name: gl_accounts_account_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gl_accounts_account_type_index ON public.gl_accounts USING btree (account_type);


--
-- Name: gl_entries_chart_of_account_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gl_entries_chart_of_account_id_posting_date_index ON public.gl_entries USING btree (chart_of_account_id, posting_date);


--
-- Name: gl_entries_is_closing_entry_closing_fiscal_year_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gl_entries_is_closing_entry_closing_fiscal_year_index ON public.gl_entries USING btree (is_closing_entry, closing_fiscal_year);


--
-- Name: gl_entries_shortcut_dimension_1_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gl_entries_shortcut_dimension_1_code_index ON public.gl_entries USING btree (shortcut_dimension_1_code);


--
-- Name: gl_entries_shortcut_dimension_2_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gl_entries_shortcut_dimension_2_code_index ON public.gl_entries USING btree (shortcut_dimension_2_code);


--
-- Name: gl_entries_sourceable_type_sourceable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gl_entries_sourceable_type_sourceable_id_index ON public.gl_entries USING btree (sourceable_type, sourceable_id);


--
-- Name: gl_entries_transaction_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gl_entries_transaction_number_index ON public.gl_entries USING btree (transaction_number);


--
-- Name: idx_currency_current; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_currency_current ON public.currency_exchange_rates USING btree (currency_id, is_current);


--
-- Name: idx_date_effective; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_date_effective ON public.pricing_master USING btree (start_date, end_date, status);


--
-- Name: idx_inventory_flow; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_inventory_flow ON public.warehouse_entries USING btree (item_id, location_id, bin_id, lot_no, serial_no);


--
-- Name: idx_item_price; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_item_price ON public.pricing_master USING btree (item_id, variant_code, unit_of_measure_code, currency_code, status);


--
-- Name: idx_price_lookup; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_price_lookup ON public.pricing_master USING btree (price_list_type, customer_id, pricing_group_id, item_id, status);


--
-- Name: idx_version; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_version ON public.pricing_master USING btree (price_list_code, is_current_version);


--
-- Name: inventory_adjustment_lines_item_id_location_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventory_adjustment_lines_item_id_location_code_index ON public.inventory_adjustment_lines USING btree (item_id, location_code);


--
-- Name: inventory_adjustment_lines_lot_no_serial_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventory_adjustment_lines_lot_no_serial_no_index ON public.inventory_adjustment_lines USING btree (lot_no, serial_no);


--
-- Name: item_category_assignments_category_id_is_primary_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_category_assignments_category_id_is_primary_index ON public.item_category_assignments USING btree (category_id, is_primary);


--
-- Name: item_category_assignments_item_id_is_primary_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_category_assignments_item_id_is_primary_index ON public.item_category_assignments USING btree (item_id, is_primary);


--
-- Name: item_journal_lines_posting_date_item_id_location_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_journal_lines_posting_date_item_id_location_id_index ON public.item_journal_lines USING btree (posting_date, item_id, location_id);


--
-- Name: item_ledger_entries_item_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_ledger_entries_item_id_posting_date_index ON public.item_ledger_entries USING btree (item_id, posting_date);


--
-- Name: item_ledger_entries_location_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_ledger_entries_location_id_posting_date_index ON public.item_ledger_entries USING btree (location_id, posting_date);


--
-- Name: item_ledger_entries_source_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_ledger_entries_source_id_index ON public.item_ledger_entries USING btree (source_id);


--
-- Name: item_ledger_entries_source_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_ledger_entries_source_type_index ON public.item_ledger_entries USING btree (source_type);


--
-- Name: item_lots_expiry_date_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_lots_expiry_date_status_index ON public.item_lots USING btree (expiry_date, status);


--
-- Name: item_skus_sku_code_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_skus_sku_code_is_active_index ON public.item_skus USING btree (sku_code, is_active);


--
-- Name: item_tracking_lines_item_lot; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_tracking_lines_item_lot ON public.item_tracking_lines USING btree (item_no, lot_no);


--
-- Name: item_tracking_lines_item_sn; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_tracking_lines_item_sn ON public.item_tracking_lines USING btree (item_no, serial_no);


--
-- Name: item_tracking_lines_source; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_tracking_lines_source ON public.item_tracking_lines USING btree (source_type, source_id);


--
-- Name: item_uom_assignments_item_id_uom_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_uom_assignments_item_id_uom_type_index ON public.item_uom_assignments USING btree (item_id, uom_type);


--
-- Name: item_uom_assignments_uom_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX item_uom_assignments_uom_id_index ON public.item_uom_assignments USING btree (uom_id);


--
-- Name: items_sku_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX items_sku_id_index ON public.items USING btree (sku_id);


--
-- Name: items_sku_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX items_sku_index ON public.items USING btree (sku);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: locations_code_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX locations_code_is_active_index ON public.locations USING btree (code, is_active);


--
-- Name: maintenance_contracts_contract_type_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_contracts_contract_type_status_index ON public.maintenance_contracts USING btree (contract_type, status);


--
-- Name: maintenance_contracts_fa_class_id_fa_location_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_contracts_fa_class_id_fa_location_id_index ON public.maintenance_contracts USING btree (fa_class_id, fa_location_id);


--
-- Name: maintenance_contracts_start_date_end_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_contracts_start_date_end_date_index ON public.maintenance_contracts USING btree (start_date, end_date);


--
-- Name: maintenance_contracts_vendor_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_contracts_vendor_id_status_index ON public.maintenance_contracts USING btree (vendor_id, status);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: number_series_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX number_series_code_index ON public.number_series USING btree (code);


--
-- Name: number_series_lines_number_series_id_starting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX number_series_lines_number_series_id_starting_date_index ON public.number_series_lines USING btree (number_series_id, starting_date);


--
-- Name: number_series_module_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX number_series_module_is_active_index ON public.number_series USING btree (module, is_active);


--
-- Name: number_series_year_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX number_series_year_index ON public.number_series USING btree (year);


--
-- Name: payment_applications_document_type_document_id_reversed_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payment_applications_document_type_document_id_reversed_index ON public.payment_applications USING btree (document_type, document_id, reversed);


--
-- Name: payment_applications_payment_id_document_type_document_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payment_applications_payment_id_document_type_document_id_index ON public.payment_applications USING btree (payment_id, document_type, document_id);


--
-- Name: payment_terms_blocked_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payment_terms_blocked_index ON public.payment_terms USING btree (blocked);


--
-- Name: payment_terms_calculation_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payment_terms_calculation_type_index ON public.payment_terms USING btree (calculation_type);


--
-- Name: payment_terms_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payment_terms_is_active_index ON public.payment_terms USING btree (is_active);


--
-- Name: payments_bank_account_id_reconciled_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_bank_account_id_reconciled_index ON public.payments USING btree (bank_account_id, reconciled);


--
-- Name: payments_external_reference_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_external_reference_index ON public.payments USING btree (external_reference);


--
-- Name: payments_party_type_party_id_payment_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_party_type_party_id_payment_date_index ON public.payments USING btree (party_type, party_id, payment_date);


--
-- Name: payments_payment_direction_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_payment_direction_status_index ON public.payments USING btree (payment_direction, status);


--
-- Name: payments_payment_method_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_payment_method_status_index ON public.payments USING btree (payment_method, status);


--
-- Name: physical_inventory_lines_item_id_location_code_bin_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX physical_inventory_lines_item_id_location_code_bin_code_index ON public.physical_inventory_lines USING btree (item_id, location_code, bin_code);


--
-- Name: physical_inventory_lines_lot_no_serial_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX physical_inventory_lines_lot_no_serial_no_index ON public.physical_inventory_lines USING btree (lot_no, serial_no);


--
-- Name: poc_order_bom_level_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX poc_order_bom_level_idx ON public.production_order_components USING btree (production_order_id, bom_level);


--
-- Name: poc_source_bom_code_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX poc_source_bom_code_idx ON public.production_order_components USING btree (source_bom_code);


--
-- Name: posted_purchase_credit_memo_lines_credit_memo_id_line_number_in; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_purchase_credit_memo_lines_credit_memo_id_line_number_in ON public.posted_purchase_credit_memo_lines USING btree (credit_memo_id, line_number);


--
-- Name: posted_purchase_credit_memos_corrects_invoice_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_purchase_credit_memos_corrects_invoice_id_index ON public.posted_purchase_credit_memos USING btree (corrects_invoice_id);


--
-- Name: posted_purchase_credit_memos_general_business_posting_group_id_; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_purchase_credit_memos_general_business_posting_group_id_ ON public.posted_purchase_credit_memos USING btree (general_business_posting_group_id);


--
-- Name: posted_purchase_credit_memos_posted_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_purchase_credit_memos_posted_posting_date_index ON public.posted_purchase_credit_memos USING btree (posted, posting_date);


--
-- Name: posted_purchase_credit_memos_source_document_id_source_document; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_purchase_credit_memos_source_document_id_source_document ON public.posted_purchase_credit_memos USING btree (source_document_id, source_document_type);


--
-- Name: posted_purchase_credit_memos_vendor_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_purchase_credit_memos_vendor_id_posting_date_index ON public.posted_purchase_credit_memos USING btree (vendor_id, posting_date);


--
-- Name: posted_purchase_credit_memos_vendor_posting_group_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_purchase_credit_memos_vendor_posting_group_id_index ON public.posted_purchase_credit_memos USING btree (vendor_posting_group_id);


--
-- Name: posted_purchase_invoices_order_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_purchase_invoices_order_id_posting_date_index ON public.posted_purchase_invoices USING btree (order_id, posting_date);


--
-- Name: posted_purchase_invoices_vendor_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_purchase_invoices_vendor_id_posting_date_index ON public.posted_purchase_invoices USING btree (vendor_id, posting_date);


--
-- Name: posted_sales_credit_memo_lines_corrected_invoice_line_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_credit_memo_lines_corrected_invoice_line_id_index ON public.posted_sales_credit_memo_lines USING btree (corrected_invoice_line_id);


--
-- Name: posted_sales_credit_memo_lines_item_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_credit_memo_lines_item_id_posting_date_index ON public.posted_sales_credit_memo_lines USING btree (item_id, posting_date);


--
-- Name: posted_sales_credit_memo_lines_posted_sales_credit_memo_id_line; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_credit_memo_lines_posted_sales_credit_memo_id_line ON public.posted_sales_credit_memo_lines USING btree (posted_sales_credit_memo_id, line_number);


--
-- Name: posted_sales_credit_memos_corrected_invoice_id_posting_date_ind; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_credit_memos_corrected_invoice_id_posting_date_ind ON public.posted_sales_credit_memos USING btree (corrected_invoice_id, posting_date);


--
-- Name: posted_sales_credit_memos_customer_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_credit_memos_customer_id_posting_date_index ON public.posted_sales_credit_memos USING btree (customer_id, posting_date);


--
-- Name: posted_sales_credit_memos_fully_applied_remaining_amount_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_credit_memos_fully_applied_remaining_amount_index ON public.posted_sales_credit_memos USING btree (fully_applied, remaining_amount);


--
-- Name: posted_sales_credit_memos_posting_date_document_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_credit_memos_posting_date_document_number_index ON public.posted_sales_credit_memos USING btree (posting_date, document_number);


--
-- Name: posted_sales_invoice_lines_item_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_invoice_lines_item_id_posting_date_index ON public.posted_sales_invoice_lines USING btree (item_id, posting_date);


--
-- Name: posted_sales_invoice_lines_posted_sales_invoice_id_line_number_; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_invoice_lines_posted_sales_invoice_id_line_number_ ON public.posted_sales_invoice_lines USING btree (posted_sales_invoice_id, line_number);


--
-- Name: posted_sales_invoices_customer_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_invoices_customer_id_posting_date_index ON public.posted_sales_invoices USING btree (customer_id, posting_date);


--
-- Name: posted_sales_invoices_order_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_invoices_order_id_posting_date_index ON public.posted_sales_invoices USING btree (order_id, posting_date);


--
-- Name: posted_sales_invoices_paid_in_full_due_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_invoices_paid_in_full_due_date_index ON public.posted_sales_invoices USING btree (paid_in_full, due_date);


--
-- Name: posted_sales_invoices_posting_date_document_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posted_sales_invoices_posting_date_document_number_index ON public.posted_sales_invoices USING btree (posting_date, document_number);


--
-- Name: price_lists_item_id_customer_group_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX price_lists_item_id_customer_group_id_index ON public.price_lists USING btree (item_id, customer_group_id);


--
-- Name: price_lists_item_id_customer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX price_lists_item_id_customer_id_index ON public.price_lists USING btree (item_id, customer_id);


--
-- Name: pricing_groups_code_blocked_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pricing_groups_code_blocked_index ON public.pricing_groups USING btree (code, blocked);


--
-- Name: pricing_groups_start_date_end_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pricing_groups_start_date_end_date_index ON public.pricing_groups USING btree (start_date, end_date);


--
-- Name: pricing_master_price_list_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pricing_master_price_list_code_index ON public.pricing_master USING btree (price_list_code);


--
-- Name: production_bom_lines_production_bom_id_line_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_bom_lines_production_bom_id_line_number_index ON public.production_bom_lines USING btree (production_bom_id, line_number);


--
-- Name: production_bom_versions_starting_date_ending_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_bom_versions_starting_date_ending_date_index ON public.production_bom_versions USING btree (starting_date, ending_date);


--
-- Name: production_bom_versions_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_bom_versions_status_index ON public.production_bom_versions USING btree (status);


--
-- Name: production_journal_lines_posting_date_work_center_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_journal_lines_posting_date_work_center_id_index ON public.production_journal_lines USING btree (posting_date, work_center_id);


--
-- Name: production_journal_lines_production_order_id_entry_type_line_st; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_journal_lines_production_order_id_entry_type_line_st ON public.production_journal_lines USING btree (production_order_id, entry_type, line_status);


--
-- Name: production_order_components_item_id_production_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_order_components_item_id_production_order_id_index ON public.production_order_components USING btree (item_id, production_order_id);


--
-- Name: production_order_components_production_order_id_line_number_ind; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_order_components_production_order_id_line_number_ind ON public.production_order_components USING btree (production_order_id, line_number);


--
-- Name: production_order_lines_due_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_order_lines_due_date_index ON public.production_order_lines USING btree (due_date);


--
-- Name: production_order_lines_item_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_order_lines_item_id_index ON public.production_order_lines USING btree (item_id);


--
-- Name: production_order_routing_lines_production_order_id_line_number_; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_order_routing_lines_production_order_id_line_number_ ON public.production_order_routing_lines USING btree (production_order_id, line_number);


--
-- Name: production_orders_item_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_orders_item_id_status_index ON public.production_orders USING btree (item_id, status);


--
-- Name: production_orders_source_id_source_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_orders_source_id_source_type_index ON public.production_orders USING btree (source_id, source_type);


--
-- Name: production_orders_status_due_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_orders_status_due_date_index ON public.production_orders USING btree (status, due_date);


--
-- Name: purchase_invoice_lines_gl_account_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_invoice_lines_gl_account_id_posting_date_index ON public.purchase_invoice_lines USING btree (gl_account_id, posting_date);


--
-- Name: purchase_invoice_lines_item_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_invoice_lines_item_id_posting_date_index ON public.purchase_invoice_lines USING btree (item_id, posting_date);


--
-- Name: purchase_invoice_lines_purchase_invoice_id_line_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_invoice_lines_purchase_invoice_id_line_number_index ON public.purchase_invoice_lines USING btree (purchase_invoice_id, line_number);


--
-- Name: purchase_invoices_order_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_invoices_order_id_posting_date_index ON public.purchase_invoices USING btree (order_id, posting_date);


--
-- Name: purchase_invoices_paid_in_full_due_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_invoices_paid_in_full_due_date_index ON public.purchase_invoices USING btree (paid_in_full, due_date);


--
-- Name: purchase_invoices_posting_date_document_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_invoices_posting_date_document_number_index ON public.purchase_invoices USING btree (posting_date, document_number);


--
-- Name: purchase_invoices_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_invoices_status_index ON public.purchase_invoices USING btree (status);


--
-- Name: purchase_invoices_vendor_id_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_invoices_vendor_id_posting_date_index ON public.purchase_invoices USING btree (vendor_id, posting_date);


--
-- Name: purchase_order_lines_item_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_order_lines_item_id_index ON public.purchase_order_lines USING btree (item_id);


--
-- Name: purchase_orders_order_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_orders_order_date_index ON public.purchase_orders USING btree (order_date);


--
-- Name: purchase_orders_order_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_orders_order_number_index ON public.purchase_orders USING btree (order_number);


--
-- Name: purchase_orders_order_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_orders_order_type_index ON public.purchase_orders USING btree (order_type);


--
-- Name: purchase_orders_order_type_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_orders_order_type_status_index ON public.purchase_orders USING btree (order_type, status);


--
-- Name: purchase_orders_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_orders_status_index ON public.purchase_orders USING btree (status);


--
-- Name: purchase_orders_vendor_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_orders_vendor_id_index ON public.purchase_orders USING btree (vendor_id);


--
-- Name: purchase_prices_vendor_id_item_id_starting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_prices_vendor_id_item_id_starting_date_index ON public.purchase_prices USING btree (vendor_id, item_id, starting_date);


--
-- Name: purchase_quote_approval_entries_approver_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_quote_approval_entries_approver_id_index ON public.purchase_quote_approval_entries USING btree (approver_id);


--
-- Name: purchase_quote_approval_entries_purchase_quote_id_sequence_no_i; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_quote_approval_entries_purchase_quote_id_sequence_no_i ON public.purchase_quote_approval_entries USING btree (purchase_quote_id, sequence_no);


--
-- Name: purchase_quote_approval_entries_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_quote_approval_entries_status_index ON public.purchase_quote_approval_entries USING btree (status);


--
-- Name: purchase_quote_archives_archived_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_quote_archives_archived_at_index ON public.purchase_quote_archives USING btree (archived_at);


--
-- Name: purchase_quote_archives_document_no_version_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_quote_archives_document_no_version_no_index ON public.purchase_quote_archives USING btree (document_no, version_no);


--
-- Name: purchase_quote_line_archives_type_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_quote_line_archives_type_no_index ON public.purchase_quote_line_archives USING btree (type, no);


--
-- Name: purchase_quote_lines_type_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_quote_lines_type_no_index ON public.purchase_quote_lines USING btree (type, no);


--
-- Name: purchase_quotes_document_no_document_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_quotes_document_no_document_type_index ON public.purchase_quotes USING btree (document_no, document_type);


--
-- Name: purchase_quotes_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_quotes_status_index ON public.purchase_quotes USING btree (status);


--
-- Name: purchase_quotes_vendor_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_quotes_vendor_id_status_index ON public.purchase_quotes USING btree (vendor_id, status);


--
-- Name: purchase_receipt_lines_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_receipt_lines_no_index ON public.purchase_receipt_lines USING btree (no);


--
-- Name: purchase_receipt_lines_purchase_receipt_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_receipt_lines_purchase_receipt_id_index ON public.purchase_receipt_lines USING btree (purchase_receipt_id);


--
-- Name: purchase_receipt_lines_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_receipt_lines_type_index ON public.purchase_receipt_lines USING btree (type);


--
-- Name: purchase_receipts_document_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_receipts_document_number_index ON public.purchase_receipts USING btree (document_number);


--
-- Name: purchase_receipts_posted_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_receipts_posted_index ON public.purchase_receipts USING btree (posted);


--
-- Name: purchase_receipts_purchase_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_receipts_purchase_order_id_index ON public.purchase_receipts USING btree (purchase_order_id);


--
-- Name: purchase_receipts_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_receipts_status_index ON public.purchase_receipts USING btree (status);


--
-- Name: purchase_receipts_vendor_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_receipts_vendor_id_index ON public.purchase_receipts USING btree (vendor_id);


--
-- Name: reserv_entry_binding; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reserv_entry_binding ON public.reservation_entries USING btree (binding_entry_no);


--
-- Name: reserv_entry_item_loc; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reserv_entry_item_loc ON public.reservation_entries USING btree (item_no, location_code);


--
-- Name: reserv_entry_no; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reserv_entry_no ON public.reservation_entries USING btree (entry_no);


--
-- Name: reserv_entry_source; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reserv_entry_source ON public.reservation_entries USING btree (source_type, source_id);


--
-- Name: reserv_entry_tracking; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reserv_entry_tracking ON public.reservation_entries USING btree (item_no, lot_no, serial_no);


--
-- Name: routing_lines_routing_id_line_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX routing_lines_routing_id_line_number_index ON public.routing_lines USING btree (routing_id, line_number);


--
-- Name: routing_versions_routing_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX routing_versions_routing_id_status_index ON public.routing_versions USING btree (routing_id, status);


--
-- Name: routing_versions_starting_date_ending_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX routing_versions_starting_date_ending_date_index ON public.routing_versions USING btree (starting_date, ending_date);


--
-- Name: sales_credit_memo_lines_sales_credit_memo_id_line_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_credit_memo_lines_sales_credit_memo_id_line_no_index ON public.sales_credit_memo_lines USING btree (sales_credit_memo_id, line_no);


--
-- Name: sales_order_lines_item_id_line_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_order_lines_item_id_line_status_index ON public.sales_order_lines USING btree (item_id, line_status);


--
-- Name: sales_order_lines_planned_delivery_date_line_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_order_lines_planned_delivery_date_line_status_index ON public.sales_order_lines USING btree (planned_delivery_date, line_status);


--
-- Name: sales_orders_customer_id_order_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_orders_customer_id_order_date_index ON public.sales_orders USING btree (customer_id, order_date);


--
-- Name: sales_orders_external_document_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_orders_external_document_number_index ON public.sales_orders USING btree (external_document_number);


--
-- Name: sales_orders_shipment_date_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_orders_shipment_date_status_index ON public.sales_orders USING btree (shipment_date, status);


--
-- Name: sales_orders_status_location_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_orders_status_location_id_index ON public.sales_orders USING btree (status, location_id);


--
-- Name: sales_shipment_headers_bill_to_customer_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_shipment_headers_bill_to_customer_no_index ON public.sales_shipment_headers USING btree (bill_to_customer_no);


--
-- Name: sales_shipment_headers_order_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_shipment_headers_order_no_index ON public.sales_shipment_headers USING btree (order_no);


--
-- Name: sales_shipment_headers_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_shipment_headers_posting_date_index ON public.sales_shipment_headers USING btree (posting_date);


--
-- Name: sales_shipment_headers_sell_to_customer_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_shipment_headers_sell_to_customer_no_index ON public.sales_shipment_headers USING btree (sell_to_customer_no);


--
-- Name: sales_shipment_headers_sell_to_customer_no_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_shipment_headers_sell_to_customer_no_posting_date_index ON public.sales_shipment_headers USING btree (sell_to_customer_no, posting_date);


--
-- Name: sales_shipment_lines_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_shipment_lines_no_index ON public.sales_shipment_lines USING btree (no);


--
-- Name: sales_shipment_lines_order_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_shipment_lines_order_no_index ON public.sales_shipment_lines USING btree (order_no);


--
-- Name: sales_shipment_lines_sales_shipment_header_id_line_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_shipment_lines_sales_shipment_header_id_line_no_index ON public.sales_shipment_lines USING btree (sales_shipment_header_id, line_no);


--
-- Name: sales_shipment_lines_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_shipment_lines_type_index ON public.sales_shipment_lines USING btree (type);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: shipment_methods_blocked_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX shipment_methods_blocked_index ON public.shipment_methods USING btree (blocked);


--
-- Name: shipment_methods_incoterm_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX shipment_methods_incoterm_code_index ON public.shipment_methods USING btree (incoterm_code);


--
-- Name: shipment_methods_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX shipment_methods_is_active_index ON public.shipment_methods USING btree (is_active);


--
-- Name: shipping_agents_blocked_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX shipping_agents_blocked_index ON public.shipping_agents USING btree (blocked);


--
-- Name: shipping_agents_country_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX shipping_agents_country_code_index ON public.shipping_agents USING btree (country_code);


--
-- Name: shipping_agents_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX shipping_agents_is_active_index ON public.shipping_agents USING btree (is_active);


--
-- Name: unit_of_measures_uom_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX unit_of_measures_uom_code_index ON public.unit_of_measures USING btree (uom_code);


--
-- Name: value_entries_cost_adjusted_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX value_entries_cost_adjusted_index ON public.value_entries USING btree (cost_adjusted);


--
-- Name: value_entries_gl_posted_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX value_entries_gl_posted_index ON public.value_entries USING btree (gl_posted);


--
-- Name: value_entries_item_ledger_entry_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX value_entries_item_ledger_entry_no_index ON public.value_entries USING btree (item_ledger_entry_no);


--
-- Name: value_entries_item_ledger_entry_type_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX value_entries_item_ledger_entry_type_posting_date_index ON public.value_entries USING btree (item_ledger_entry_type, posting_date);


--
-- Name: value_entries_item_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX value_entries_item_no_index ON public.value_entries USING btree (item_no);


--
-- Name: value_entries_item_no_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX value_entries_item_no_posting_date_index ON public.value_entries USING btree (item_no, posting_date);


--
-- Name: value_entries_posting_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX value_entries_posting_date_index ON public.value_entries USING btree (posting_date);


--
-- Name: value_entries_production_order_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX value_entries_production_order_no_index ON public.value_entries USING btree (production_order_no);


--
-- Name: value_entries_source_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX value_entries_source_no_index ON public.value_entries USING btree (source_no);


--
-- Name: value_entries_source_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX value_entries_source_type_index ON public.value_entries USING btree (source_type);


--
-- Name: value_entries_source_type_source_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX value_entries_source_type_source_no_index ON public.value_entries USING btree (source_type, source_no);


--
-- Name: vat_masters_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vat_masters_code_index ON public.vat_masters USING btree (code);


--
-- Name: vat_masters_percentage_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vat_masters_percentage_index ON public.vat_masters USING btree (percentage);


--
-- Name: vendor_invoice_lines_purchase_order_id_purchase_order_line_no_i; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_invoice_lines_purchase_order_id_purchase_order_line_no_i ON public.vendor_invoice_lines USING btree (purchase_order_id, purchase_order_line_no);


--
-- Name: vendor_invoices_capex_project_id_capitalized_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_invoices_capex_project_id_capitalized_index ON public.vendor_invoices USING btree (capex_project_id, capitalized);


--
-- Name: vendor_invoices_external_document_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_invoices_external_document_no_index ON public.vendor_invoices USING btree (external_document_no);


--
-- Name: vendor_invoices_posting_date_document_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_invoices_posting_date_document_type_index ON public.vendor_invoices USING btree (posting_date, document_type);


--
-- Name: vendor_invoices_source_document_type_source_document_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_invoices_source_document_type_source_document_id_index ON public.vendor_invoices USING btree (source_document_type, source_document_id);


--
-- Name: vendor_invoices_vendor_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_invoices_vendor_id_status_index ON public.vendor_invoices USING btree (vendor_id, status);


--
-- Name: vendor_items_item_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_items_item_id_index ON public.vendor_items USING btree (item_id);


--
-- Name: vendor_items_item_id_is_preferred_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_items_item_id_is_preferred_index ON public.vendor_items USING btree (item_id, is_preferred);


--
-- Name: vendor_items_vendor_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_items_vendor_id_is_active_index ON public.vendor_items USING btree (vendor_id, is_active);


--
-- Name: vendor_items_vendor_item_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_items_vendor_item_number_index ON public.vendor_items USING btree (vendor_item_number);


--
-- Name: vendor_ledger_entries_document_type_document_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_ledger_entries_document_type_document_number_index ON public.vendor_ledger_entries USING btree (document_type, document_number);


--
-- Name: vendor_ledger_entries_open_due_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_ledger_entries_open_due_date_index ON public.vendor_ledger_entries USING btree (open, due_date);


--
-- Name: vendor_ledger_entries_payment_discount_due_date_open_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_ledger_entries_payment_discount_due_date_open_index ON public.vendor_ledger_entries USING btree (payment_discount_due_date, open);


--
-- Name: vendor_ledger_entries_vendor_id_open_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_ledger_entries_vendor_id_open_index ON public.vendor_ledger_entries USING btree (vendor_id, open);


--
-- Name: vendor_ledger_entries_vendor_id_posting_date_entry_number_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendor_ledger_entries_vendor_id_posting_date_entry_number_index ON public.vendor_ledger_entries USING btree (vendor_id, posting_date, entry_number);


--
-- Name: vendors_gen_bus_posting_group_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendors_gen_bus_posting_group_index ON public.vendors USING btree (gen_bus_posting_group);


--
-- Name: vendors_vat_bus_posting_group_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendors_vat_bus_posting_group_index ON public.vendors USING btree (vat_bus_posting_group);


--
-- Name: vendors_vendor_posting_group_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vendors_vendor_posting_group_index ON public.vendors USING btree (vendor_posting_group);


--
-- Name: warehouse_activities_activity_type_status_location_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX warehouse_activities_activity_type_status_location_id_index ON public.warehouse_activities USING btree (activity_type, status, location_id);


--
-- Name: warehouse_activities_source_document_source_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX warehouse_activities_source_document_source_no_index ON public.warehouse_activities USING btree (source_document, source_no);


--
-- Name: warehouse_entries_document_type_document_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX warehouse_entries_document_type_document_no_index ON public.warehouse_entries USING btree (document_type, document_no);


--
-- Name: warehouse_entries_entry_timestamp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX warehouse_entries_entry_timestamp_index ON public.warehouse_entries USING btree (entry_timestamp);


--
-- Name: warehouse_journal_lines_posting_date_item_id_source_location_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX warehouse_journal_lines_posting_date_item_id_source_location_id ON public.warehouse_journal_lines USING btree (posting_date, item_id, source_location_id);


--
-- Name: warehouse_journal_lines_warehouse_activity_id_warehouse_activit; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX warehouse_journal_lines_warehouse_activity_id_warehouse_activit ON public.warehouse_journal_lines USING btree (warehouse_activity_id, warehouse_activity_line_id);


--
-- Name: warehouse_picks_source_document_source_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX warehouse_picks_source_document_source_no_index ON public.warehouse_picks USING btree (source_document, source_no);


--
-- Name: warehouse_picks_status_location_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX warehouse_picks_status_location_id_index ON public.warehouse_picks USING btree (status, location_id);


--
-- Name: warehouse_requests_source_document_source_no_source_line_no_ind; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX warehouse_requests_source_document_source_no_source_line_no_ind ON public.warehouse_requests USING btree (source_document, source_no, source_line_no);


--
-- Name: warehouse_requests_status_location_id_item_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX warehouse_requests_status_location_id_item_id_index ON public.warehouse_requests USING btree (status, location_id, item_id);


--
-- Name: account_schedule_lines account_schedule_lines_schedule_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_schedule_lines
    ADD CONSTRAINT account_schedule_lines_schedule_id_foreign FOREIGN KEY (schedule_id) REFERENCES public.account_schedules(id) ON DELETE CASCADE;


--
-- Name: accounting_periods accounting_periods_closed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accounting_periods
    ADD CONSTRAINT accounting_periods_closed_by_foreign FOREIGN KEY (closed_by) REFERENCES public.users(id);


--
-- Name: actual_overhead_costs actual_overhead_costs_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actual_overhead_costs
    ADD CONSTRAINT actual_overhead_costs_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: actual_overhead_costs actual_overhead_costs_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actual_overhead_costs
    ADD CONSTRAINT actual_overhead_costs_gl_account_id_foreign FOREIGN KEY (gl_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: actual_overhead_costs actual_overhead_costs_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actual_overhead_costs
    ADD CONSTRAINT actual_overhead_costs_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id) ON DELETE SET NULL;


--
-- Name: actual_overhead_costs actual_overhead_costs_machine_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actual_overhead_costs
    ADD CONSTRAINT actual_overhead_costs_machine_center_id_foreign FOREIGN KEY (machine_center_id) REFERENCES public.machine_centers(id) ON DELETE SET NULL;


--
-- Name: actual_overhead_costs actual_overhead_costs_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actual_overhead_costs
    ADD CONSTRAINT actual_overhead_costs_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: actual_overhead_costs actual_overhead_costs_variance_journal_batch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actual_overhead_costs
    ADD CONSTRAINT actual_overhead_costs_variance_journal_batch_id_foreign FOREIGN KEY (variance_journal_batch_id) REFERENCES public.general_journal_batches(id) ON DELETE SET NULL;


--
-- Name: actual_overhead_costs actual_overhead_costs_work_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.actual_overhead_costs
    ADD CONSTRAINT actual_overhead_costs_work_center_id_foreign FOREIGN KEY (work_center_id) REFERENCES public.work_centers(id) ON DELETE SET NULL;


--
-- Name: allocation_lines allocation_lines_allocation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocation_lines
    ADD CONSTRAINT allocation_lines_allocation_id_foreign FOREIGN KEY (allocation_id) REFERENCES public.allocations(id) ON DELETE CASCADE;


--
-- Name: allocation_lines allocation_lines_target_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocation_lines
    ADD CONSTRAINT allocation_lines_target_account_id_foreign FOREIGN KEY (target_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: approval_entries approval_entries_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_entries
    ADD CONSTRAINT approval_entries_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: approval_entries approval_entries_approver_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_entries
    ADD CONSTRAINT approval_entries_approver_id_foreign FOREIGN KEY (approver_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: approval_entries approval_entries_delegated_to_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_entries
    ADD CONSTRAINT approval_entries_delegated_to_foreign FOREIGN KEY (delegated_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: approval_entries approval_entries_rejected_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_entries
    ADD CONSTRAINT approval_entries_rejected_by_foreign FOREIGN KEY (rejected_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: approval_template_entries approval_template_entries_approval_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_template_entries
    ADD CONSTRAINT approval_template_entries_approval_template_id_foreign FOREIGN KEY (approval_template_id) REFERENCES public.approval_templates(id) ON DELETE CASCADE;


--
-- Name: approval_template_entries approval_template_entries_approver_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_template_entries
    ADD CONSTRAINT approval_template_entries_approver_id_foreign FOREIGN KEY (approver_id) REFERENCES public.users(id);


--
-- Name: approval_templates approval_templates_vendor_posting_group_filter_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.approval_templates
    ADD CONSTRAINT approval_templates_vendor_posting_group_filter_foreign FOREIGN KEY (vendor_posting_group_filter) REFERENCES public.vendor_posting_groups(id);


--
-- Name: asset_components asset_components_component_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_components
    ADD CONSTRAINT asset_components_component_asset_id_foreign FOREIGN KEY (component_asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;


--
-- Name: asset_components asset_components_main_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_components
    ADD CONSTRAINT asset_components_main_asset_id_foreign FOREIGN KEY (main_asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;


--
-- Name: asset_ledger_entries asset_ledger_entries_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_ledger_entries
    ADD CONSTRAINT asset_ledger_entries_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;


--
-- Name: asset_ledger_entries asset_ledger_entries_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_ledger_entries
    ADD CONSTRAINT asset_ledger_entries_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id);


--
-- Name: asset_ledger_entries asset_ledger_entries_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_ledger_entries
    ADD CONSTRAINT asset_ledger_entries_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: asset_maintenances asset_maintenances_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_maintenances
    ADD CONSTRAINT asset_maintenances_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;


--
-- Name: asset_maintenances asset_maintenances_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_maintenances
    ADD CONSTRAINT asset_maintenances_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: assets assets_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id);


--
-- Name: attendance_ledger_entries attendance_ledger_entries_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_ledger_entries
    ADD CONSTRAINT attendance_ledger_entries_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: attendance_ledger_entries attendance_ledger_entries_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_ledger_entries
    ADD CONSTRAINT attendance_ledger_entries_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: attendance_ledger_entries attendance_ledger_entries_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_ledger_entries
    ADD CONSTRAINT attendance_ledger_entries_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: bank_account_ledger_entries bank_account_ledger_entries_bank_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_ledger_entries
    ADD CONSTRAINT bank_account_ledger_entries_bank_account_id_foreign FOREIGN KEY (bank_account_id) REFERENCES public.bank_accounts(id);


--
-- Name: bank_account_ledger_entries bank_account_ledger_entries_customer_ledger_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_ledger_entries
    ADD CONSTRAINT bank_account_ledger_entries_customer_ledger_entry_id_foreign FOREIGN KEY (customer_ledger_entry_id) REFERENCES public.customer_ledger_entries(id);


--
-- Name: bank_account_ledger_entries bank_account_ledger_entries_gl_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_ledger_entries
    ADD CONSTRAINT bank_account_ledger_entries_gl_entry_id_foreign FOREIGN KEY (gl_entry_id) REFERENCES public.gl_entries(id);


--
-- Name: bank_account_ledger_entries bank_account_ledger_entries_reconciled_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_ledger_entries
    ADD CONSTRAINT bank_account_ledger_entries_reconciled_by_foreign FOREIGN KEY (reconciled_by) REFERENCES public.users(id);


--
-- Name: bank_account_ledger_entries bank_account_ledger_entries_transfer_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_ledger_entries
    ADD CONSTRAINT bank_account_ledger_entries_transfer_entry_id_foreign FOREIGN KEY (transfer_entry_id) REFERENCES public.bank_account_ledger_entries(id);


--
-- Name: bank_account_ledger_entries bank_account_ledger_entries_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_ledger_entries
    ADD CONSTRAINT bank_account_ledger_entries_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: bank_account_ledger_entries bank_account_ledger_entries_vendor_ledger_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_ledger_entries
    ADD CONSTRAINT bank_account_ledger_entries_vendor_ledger_entry_id_foreign FOREIGN KEY (vendor_ledger_entry_id) REFERENCES public.vendor_ledger_entries(id);


--
-- Name: bank_account_ledger_entries bank_account_ledger_entries_voided_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_ledger_entries
    ADD CONSTRAINT bank_account_ledger_entries_voided_by_foreign FOREIGN KEY (voided_by) REFERENCES public.users(id);


--
-- Name: bank_account_statement_lines bank_account_statement_lines_bank_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_statement_lines
    ADD CONSTRAINT bank_account_statement_lines_bank_account_id_foreign FOREIGN KEY (bank_account_id) REFERENCES public.bank_accounts(id);


--
-- Name: bank_account_statement_lines bank_account_statement_lines_bank_account_ledger_entry_id_forei; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_account_statement_lines
    ADD CONSTRAINT bank_account_statement_lines_bank_account_ledger_entry_id_forei FOREIGN KEY (bank_account_ledger_entry_id) REFERENCES public.bank_account_ledger_entries(id);


--
-- Name: bank_accounts bank_accounts_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_accounts
    ADD CONSTRAINT bank_accounts_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id);


--
-- Name: bank_accounts bank_accounts_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_accounts
    ADD CONSTRAINT bank_accounts_gl_account_id_foreign FOREIGN KEY (gl_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: bank_reconciliations bank_reconciliations_bank_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_reconciliations
    ADD CONSTRAINT bank_reconciliations_bank_account_id_foreign FOREIGN KEY (bank_account_id) REFERENCES public.bank_accounts(id);


--
-- Name: bank_reconciliations bank_reconciliations_reconciled_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_reconciliations
    ADD CONSTRAINT bank_reconciliations_reconciled_by_foreign FOREIGN KEY (reconciled_by) REFERENCES public.users(id);


--
-- Name: bin_contents bin_contents_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bin_contents
    ADD CONSTRAINT bin_contents_bin_id_foreign FOREIGN KEY (bin_id) REFERENCES public.bins(id) ON DELETE CASCADE;


--
-- Name: bin_contents bin_contents_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bin_contents
    ADD CONSTRAINT bin_contents_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: bin_contents bin_contents_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bin_contents
    ADD CONSTRAINT bin_contents_zone_id_foreign FOREIGN KEY (zone_id) REFERENCES public.zones(id) ON DELETE SET NULL;


--
-- Name: bins bins_dedicated_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bins
    ADD CONSTRAINT bins_dedicated_item_id_foreign FOREIGN KEY (dedicated_item_id) REFERENCES public.items(id) ON DELETE SET NULL;


--
-- Name: bins bins_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bins
    ADD CONSTRAINT bins_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id) ON DELETE CASCADE;


--
-- Name: bins bins_uom_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bins
    ADD CONSTRAINT bins_uom_id_foreign FOREIGN KEY (uom_id) REFERENCES public.unit_of_measures(id) ON DELETE SET NULL;


--
-- Name: bins bins_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bins
    ADD CONSTRAINT bins_zone_id_foreign FOREIGN KEY (zone_id) REFERENCES public.zones(id) ON DELETE SET NULL;


--
-- Name: blanket_order_lines blanket_order_lines_blanket_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_order_lines
    ADD CONSTRAINT blanket_order_lines_blanket_order_id_foreign FOREIGN KEY (blanket_order_id) REFERENCES public.blanket_orders(id) ON DELETE CASCADE;


--
-- Name: blanket_order_lines blanket_order_lines_purchase_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_order_lines
    ADD CONSTRAINT blanket_order_lines_purchase_order_id_foreign FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id) ON DELETE SET NULL;


--
-- Name: blanket_order_lines blanket_order_lines_sales_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_order_lines
    ADD CONSTRAINT blanket_order_lines_sales_order_id_foreign FOREIGN KEY (sales_order_id) REFERENCES public.sales_orders(id) ON DELETE SET NULL;


--
-- Name: blanket_order_lines blanket_order_lines_sales_order_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_order_lines
    ADD CONSTRAINT blanket_order_lines_sales_order_line_id_foreign FOREIGN KEY (sales_order_line_id) REFERENCES public.sales_order_lines(id) ON DELETE SET NULL;


--
-- Name: blanket_orders blanket_orders_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_orders
    ADD CONSTRAINT blanket_orders_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: blanket_orders blanket_orders_buyer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_orders
    ADD CONSTRAINT blanket_orders_buyer_id_foreign FOREIGN KEY (buyer_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: blanket_orders blanket_orders_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_orders
    ADD CONSTRAINT blanket_orders_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: blanket_orders blanket_orders_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_orders
    ADD CONSTRAINT blanket_orders_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: blanket_orders blanket_orders_last_modified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_orders
    ADD CONSTRAINT blanket_orders_last_modified_by_foreign FOREIGN KEY (last_modified_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: blanket_orders blanket_orders_released_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_orders
    ADD CONSTRAINT blanket_orders_released_by_foreign FOREIGN KEY (released_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: blanket_orders blanket_orders_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blanket_orders
    ADD CONSTRAINT blanket_orders_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id) ON DELETE CASCADE;


--
-- Name: capacity_ledger_entries capacity_ledger_entries_capex_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capacity_ledger_entries
    ADD CONSTRAINT capacity_ledger_entries_capex_project_id_foreign FOREIGN KEY (capex_project_id) REFERENCES public.capex_projects(id) ON DELETE SET NULL;


--
-- Name: capacity_ledger_entries capacity_ledger_entries_fixed_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capacity_ledger_entries
    ADD CONSTRAINT capacity_ledger_entries_fixed_asset_id_foreign FOREIGN KEY (fixed_asset_id) REFERENCES public.fixed_assets(id) ON DELETE SET NULL;


--
-- Name: capacity_ledger_entries capacity_ledger_entries_machine_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capacity_ledger_entries
    ADD CONSTRAINT capacity_ledger_entries_machine_center_id_foreign FOREIGN KEY (machine_center_id) REFERENCES public.machine_centers(id);


--
-- Name: capacity_ledger_entries capacity_ledger_entries_production_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capacity_ledger_entries
    ADD CONSTRAINT capacity_ledger_entries_production_order_id_foreign FOREIGN KEY (production_order_id) REFERENCES public.production_orders(id);


--
-- Name: capacity_ledger_entries capacity_ledger_entries_routing_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capacity_ledger_entries
    ADD CONSTRAINT capacity_ledger_entries_routing_line_id_foreign FOREIGN KEY (routing_line_id) REFERENCES public.production_order_routing_lines(id);


--
-- Name: capacity_ledger_entries capacity_ledger_entries_work_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capacity_ledger_entries
    ADD CONSTRAINT capacity_ledger_entries_work_center_id_foreign FOREIGN KEY (work_center_id) REFERENCES public.work_centers(id);


--
-- Name: capex_project_lines capex_project_lines_capacity_ledger_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_project_lines
    ADD CONSTRAINT capex_project_lines_capacity_ledger_entry_id_foreign FOREIGN KEY (capacity_ledger_entry_id) REFERENCES public.capacity_ledger_entries(id);


--
-- Name: capex_project_lines capex_project_lines_capex_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_project_lines
    ADD CONSTRAINT capex_project_lines_capex_project_id_foreign FOREIGN KEY (capex_project_id) REFERENCES public.capex_projects(id) ON DELETE CASCADE;


--
-- Name: capex_project_lines capex_project_lines_capitalized_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_project_lines
    ADD CONSTRAINT capex_project_lines_capitalized_by_foreign FOREIGN KEY (capitalized_by) REFERENCES public.users(id);


--
-- Name: capex_project_lines capex_project_lines_production_order_component_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_project_lines
    ADD CONSTRAINT capex_project_lines_production_order_component_id_foreign FOREIGN KEY (production_order_component_id) REFERENCES public.production_order_components(id);


--
-- Name: capex_project_lines capex_project_lines_production_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_project_lines
    ADD CONSTRAINT capex_project_lines_production_order_id_foreign FOREIGN KEY (production_order_id) REFERENCES public.production_orders(id);


--
-- Name: capex_project_lines capex_project_lines_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_project_lines
    ADD CONSTRAINT capex_project_lines_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: capex_projects capex_projects_approver_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_projects
    ADD CONSTRAINT capex_projects_approver_id_foreign FOREIGN KEY (approver_id) REFERENCES public.users(id);


--
-- Name: capex_projects capex_projects_capex_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_projects
    ADD CONSTRAINT capex_projects_capex_gl_account_id_foreign FOREIGN KEY (capex_gl_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: capex_projects capex_projects_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_projects
    ADD CONSTRAINT capex_projects_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: capex_projects capex_projects_last_modified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_projects
    ADD CONSTRAINT capex_projects_last_modified_by_foreign FOREIGN KEY (last_modified_by) REFERENCES public.users(id);


--
-- Name: capex_projects capex_projects_project_manager_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_projects
    ADD CONSTRAINT capex_projects_project_manager_id_foreign FOREIGN KEY (project_manager_id) REFERENCES public.users(id);


--
-- Name: capex_projects capex_projects_wip_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.capex_projects
    ADD CONSTRAINT capex_projects_wip_gl_account_id_foreign FOREIGN KEY (wip_gl_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: cash_receipt_lines cash_receipt_lines_bank_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cash_receipt_lines
    ADD CONSTRAINT cash_receipt_lines_bank_account_id_foreign FOREIGN KEY (bank_account_id) REFERENCES public.bank_accounts(id);


--
-- Name: cash_receipt_lines cash_receipt_lines_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cash_receipt_lines
    ADD CONSTRAINT cash_receipt_lines_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: cash_receipt_lines cash_receipt_lines_journal_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cash_receipt_lines
    ADD CONSTRAINT cash_receipt_lines_journal_line_id_foreign FOREIGN KEY (journal_line_id) REFERENCES public.journal_lines(id) ON DELETE CASCADE;


--
-- Name: categories categories_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- Name: chart_of_accounts chart_of_accounts_gen_bus_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_gen_bus_posting_group_id_foreign FOREIGN KEY (gen_bus_posting_group_id) REFERENCES public.general_business_posting_groups(id) ON DELETE SET NULL;


--
-- Name: chart_of_accounts chart_of_accounts_gen_prod_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_gen_prod_posting_group_id_foreign FOREIGN KEY (gen_prod_posting_group_id) REFERENCES public.general_product_posting_groups(id) ON DELETE SET NULL;


--
-- Name: chart_of_accounts chart_of_accounts_parent_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_parent_account_id_foreign FOREIGN KEY (parent_account_id) REFERENCES public.chart_of_accounts(id) ON DELETE SET NULL;


--
-- Name: chart_of_accounts chart_of_accounts_vat_bus_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_vat_bus_posting_group_id_foreign FOREIGN KEY (vat_bus_posting_group_id) REFERENCES public.vat_business_posting_groups(id) ON DELETE SET NULL;


--
-- Name: chart_of_accounts chart_of_accounts_vat_prod_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_vat_prod_posting_group_id_foreign FOREIGN KEY (vat_prod_posting_group_id) REFERENCES public.vat_product_posting_groups(id) ON DELETE SET NULL;


--
-- Name: company_information company_information_business_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_information
    ADD CONSTRAINT company_information_business_id_foreign FOREIGN KEY (business_id) REFERENCES public.businesses(id) ON DELETE SET NULL;


--
-- Name: contacts contacts_general_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contacts
    ADD CONSTRAINT contacts_general_business_posting_group_id_foreign FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id) ON DELETE SET NULL;


--
-- Name: contacts contacts_vendor_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contacts
    ADD CONSTRAINT contacts_vendor_posting_group_id_foreign FOREIGN KEY (vendor_posting_group_id) REFERENCES public.vendor_posting_groups(id) ON DELETE SET NULL;


--
-- Name: currencies currencies_invoice_rounding_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_invoice_rounding_account_id_foreign FOREIGN KEY (invoice_rounding_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: currencies currencies_payables_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_payables_account_id_foreign FOREIGN KEY (payables_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: currencies currencies_realized_gains_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_realized_gains_account_id_foreign FOREIGN KEY (realized_gains_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: currencies currencies_realized_losses_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_realized_losses_account_id_foreign FOREIGN KEY (realized_losses_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: currencies currencies_receivables_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_receivables_account_id_foreign FOREIGN KEY (receivables_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: currencies currencies_unrealized_gains_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_unrealized_gains_account_id_foreign FOREIGN KEY (unrealized_gains_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: currencies currencies_unrealized_losses_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_unrealized_losses_account_id_foreign FOREIGN KEY (unrealized_losses_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: currency_adjustment_ledger currency_adjustment_ledger_adjustment_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_adjustment_ledger
    ADD CONSTRAINT currency_adjustment_ledger_adjustment_account_id_foreign FOREIGN KEY (adjustment_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: currency_adjustment_ledger currency_adjustment_ledger_bank_account_ledger_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_adjustment_ledger
    ADD CONSTRAINT currency_adjustment_ledger_bank_account_ledger_entry_id_foreign FOREIGN KEY (bank_account_ledger_entry_id) REFERENCES public.bank_account_ledger_entries(id);


--
-- Name: currency_adjustment_ledger currency_adjustment_ledger_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_adjustment_ledger
    ADD CONSTRAINT currency_adjustment_ledger_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: currency_adjustment_ledger currency_adjustment_ledger_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_adjustment_ledger
    ADD CONSTRAINT currency_adjustment_ledger_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id);


--
-- Name: currency_adjustment_ledger currency_adjustment_ledger_customer_ledger_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_adjustment_ledger
    ADD CONSTRAINT currency_adjustment_ledger_customer_ledger_entry_id_foreign FOREIGN KEY (customer_ledger_entry_id) REFERENCES public.customer_ledger_entries(id);


--
-- Name: currency_adjustment_ledger currency_adjustment_ledger_gl_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_adjustment_ledger
    ADD CONSTRAINT currency_adjustment_ledger_gl_entry_id_foreign FOREIGN KEY (gl_entry_id) REFERENCES public.gl_entries(id);


--
-- Name: currency_adjustment_ledger currency_adjustment_ledger_vendor_ledger_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_adjustment_ledger
    ADD CONSTRAINT currency_adjustment_ledger_vendor_ledger_entry_id_foreign FOREIGN KEY (vendor_ledger_entry_id) REFERENCES public.vendor_ledger_entries(id);


--
-- Name: currency_buffers currency_buffers_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_buffers
    ADD CONSTRAINT currency_buffers_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id);


--
-- Name: currency_exchange_rates currency_exchange_rates_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currency_exchange_rates
    ADD CONSTRAINT currency_exchange_rates_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id) ON DELETE CASCADE;


--
-- Name: customer_ledger_entries customer_ledger_entries_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_ledger_entries
    ADD CONSTRAINT customer_ledger_entries_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: customer_ledger_entries customer_ledger_entries_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_ledger_entries
    ADD CONSTRAINT customer_ledger_entries_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id) ON DELETE SET NULL;


--
-- Name: customer_ledger_entries customer_ledger_entries_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_ledger_entries
    ADD CONSTRAINT customer_ledger_entries_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: customer_ledger_entries customer_ledger_entries_customer_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_ledger_entries
    ADD CONSTRAINT customer_ledger_entries_customer_posting_group_id_foreign FOREIGN KEY (customer_posting_group_id) REFERENCES public.customer_posting_groups(id);


--
-- Name: customer_ledger_entries customer_ledger_entries_general_business_posting_group_id_forei; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_ledger_entries
    ADD CONSTRAINT customer_ledger_entries_general_business_posting_group_id_forei FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: customer_ledger_entries customer_ledger_entries_reversed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_ledger_entries
    ADD CONSTRAINT customer_ledger_entries_reversed_by_foreign FOREIGN KEY (reversed_by) REFERENCES public.users(id);


--
-- Name: customer_posting_groups customer_posting_groups_credit_rounding_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_posting_groups
    ADD CONSTRAINT customer_posting_groups_credit_rounding_account_id_foreign FOREIGN KEY (credit_rounding_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: customer_posting_groups customer_posting_groups_debit_rounding_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_posting_groups
    ADD CONSTRAINT customer_posting_groups_debit_rounding_account_id_foreign FOREIGN KEY (debit_rounding_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: customer_posting_groups customer_posting_groups_invoice_rounding_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_posting_groups
    ADD CONSTRAINT customer_posting_groups_invoice_rounding_account_id_foreign FOREIGN KEY (invoice_rounding_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: customer_posting_groups customer_posting_groups_payment_disc_credit_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_posting_groups
    ADD CONSTRAINT customer_posting_groups_payment_disc_credit_account_id_foreign FOREIGN KEY (payment_disc_credit_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: customer_posting_groups customer_posting_groups_payment_disc_debit_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_posting_groups
    ADD CONSTRAINT customer_posting_groups_payment_disc_debit_account_id_foreign FOREIGN KEY (payment_disc_debit_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: customer_posting_groups customer_posting_groups_receivables_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_posting_groups
    ADD CONSTRAINT customer_posting_groups_receivables_account_id_foreign FOREIGN KEY (receivables_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: customer_price_overrides customer_price_overrides_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_price_overrides
    ADD CONSTRAINT customer_price_overrides_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: customer_price_overrides customer_price_overrides_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_price_overrides
    ADD CONSTRAINT customer_price_overrides_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: customers customers_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE CASCADE;


--
-- Name: customers customers_customer_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_customer_group_id_foreign FOREIGN KEY (customer_group_id) REFERENCES public.customer_groups(id) ON DELETE SET NULL;


--
-- Name: customers customers_customer_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_customer_posting_group_id_foreign FOREIGN KEY (customer_posting_group_id) REFERENCES public.customer_posting_groups(id);


--
-- Name: customers customers_general_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_general_business_posting_group_id_foreign FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: customers customers_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: customers customers_pricing_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_pricing_group_id_foreign FOREIGN KEY (pricing_group_id) REFERENCES public.pricing_groups(id);


--
-- Name: customers customers_vat_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_vat_business_posting_group_id_foreign FOREIGN KEY (vat_business_posting_group_id) REFERENCES public.vat_business_posting_groups(id);


--
-- Name: department_employee department_employee_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.department_employee
    ADD CONSTRAINT department_employee_department_id_foreign FOREIGN KEY (department_id) REFERENCES public.departments(id);


--
-- Name: department_employee department_employee_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.department_employee
    ADD CONSTRAINT department_employee_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id);


--
-- Name: departments departments_approver_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_approver_id_foreign FOREIGN KEY (approver_id) REFERENCES public.users(id);


--
-- Name: departments departments_blocked_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_blocked_by_foreign FOREIGN KEY (blocked_by) REFERENCES public.users(id);


--
-- Name: departments departments_dimension_value_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_dimension_value_id_foreign FOREIGN KEY (dimension_value_id) REFERENCES public.dimension_values(id);


--
-- Name: departments departments_manager_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_manager_id_foreign FOREIGN KEY (manager_id) REFERENCES public.employees(id);


--
-- Name: departments departments_parent_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_parent_department_id_foreign FOREIGN KEY (parent_department_id) REFERENCES public.departments(id);


--
-- Name: dimension_set_entries dimension_set_entries_dimension_set_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_set_entries
    ADD CONSTRAINT dimension_set_entries_dimension_set_id_foreign FOREIGN KEY (dimension_set_id) REFERENCES public.dimension_sets(id);


--
-- Name: dimension_set_tree_nodes dimension_set_tree_nodes_dimension_set_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_set_tree_nodes
    ADD CONSTRAINT dimension_set_tree_nodes_dimension_set_id_foreign FOREIGN KEY (dimension_set_id) REFERENCES public.dimension_sets(id);


--
-- Name: dimension_set_tree_nodes dimension_set_tree_nodes_dimension_value_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_set_tree_nodes
    ADD CONSTRAINT dimension_set_tree_nodes_dimension_value_id_foreign FOREIGN KEY (dimension_value_id) REFERENCES public.dimension_values(id);


--
-- Name: dimension_value_combinations dimension_value_combinations_dimension_combination_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_value_combinations
    ADD CONSTRAINT dimension_value_combinations_dimension_combination_id_foreign FOREIGN KEY (dimension_combination_id) REFERENCES public.dimension_combinations(id);


--
-- Name: dimension_values dimension_values_dimension_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_values
    ADD CONSTRAINT dimension_values_dimension_id_foreign FOREIGN KEY (dimension_id) REFERENCES public.dimensions(id);


--
-- Name: dimension_values dimension_values_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dimension_values
    ADD CONSTRAINT dimension_values_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.dimension_values(id);


--
-- Name: document_headers document_headers_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_headers
    ADD CONSTRAINT document_headers_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: employee_bank_accounts employee_bank_accounts_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_bank_accounts
    ADD CONSTRAINT employee_bank_accounts_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id);


--
-- Name: employee_compensation employee_compensation_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_compensation
    ADD CONSTRAINT employee_compensation_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: employee_pay_codes employee_pay_codes_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_pay_codes
    ADD CONSTRAINT employee_pay_codes_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: employee_pay_codes employee_pay_codes_pay_code_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_pay_codes
    ADD CONSTRAINT employee_pay_codes_pay_code_id_foreign FOREIGN KEY (pay_code_id) REFERENCES public.pay_codes(id) ON DELETE CASCADE;


--
-- Name: employee_posting_groups employee_posting_groups_payables_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_posting_groups
    ADD CONSTRAINT employee_posting_groups_payables_account_id_foreign FOREIGN KEY (payables_account_id) REFERENCES public.chart_of_accounts(id) ON DELETE SET NULL;


--
-- Name: employee_promotion_histories employee_promotion_histories_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_promotion_histories
    ADD CONSTRAINT employee_promotion_histories_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: employee_promotion_histories employee_promotion_histories_new_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_promotion_histories
    ADD CONSTRAINT employee_promotion_histories_new_department_id_foreign FOREIGN KEY (new_department_id) REFERENCES public.departments(id) ON DELETE SET NULL;


--
-- Name: employee_promotion_histories employee_promotion_histories_old_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_promotion_histories
    ADD CONSTRAINT employee_promotion_histories_old_department_id_foreign FOREIGN KEY (old_department_id) REFERENCES public.departments(id) ON DELETE SET NULL;


--
-- Name: employee_promotion_histories employee_promotion_histories_promoted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_promotion_histories
    ADD CONSTRAINT employee_promotion_histories_promoted_by_foreign FOREIGN KEY (promoted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employee_ytd_balances employee_ytd_balances_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_ytd_balances
    ADD CONSTRAINT employee_ytd_balances_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id);


--
-- Name: employees employees_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_department_id_foreign FOREIGN KEY (department_id) REFERENCES public.departments(id) ON DELETE SET NULL;


--
-- Name: employees employees_employee_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_employee_posting_group_id_foreign FOREIGN KEY (employee_posting_group_id) REFERENCES public.employee_posting_groups(id) ON DELETE SET NULL;


--
-- Name: employees employees_payroll_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_payroll_posting_group_id_foreign FOREIGN KEY (payroll_posting_group_id) REFERENCES public.payroll_posting_groups(id) ON DELETE SET NULL;


--
-- Name: expense_allocations expense_allocations_expense_transaction_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_allocations
    ADD CONSTRAINT expense_allocations_expense_transaction_id_foreign FOREIGN KEY (expense_transaction_id) REFERENCES public.expense_transactions(id);


--
-- Name: expense_allocations expense_allocations_gl_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_allocations
    ADD CONSTRAINT expense_allocations_gl_entry_id_foreign FOREIGN KEY (gl_entry_id) REFERENCES public.gl_entries(id);


--
-- Name: expense_allocations expense_allocations_target_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_allocations
    ADD CONSTRAINT expense_allocations_target_gl_account_id_foreign FOREIGN KEY (target_gl_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: expense_budgets expense_budgets_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_budgets
    ADD CONSTRAINT expense_budgets_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id);


--
-- Name: expense_budgets expense_budgets_dimension_set_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_budgets
    ADD CONSTRAINT expense_budgets_dimension_set_id_foreign FOREIGN KEY (dimension_set_id) REFERENCES public.dimension_sets(id);


--
-- Name: expense_categories expense_categories_contra_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_categories
    ADD CONSTRAINT expense_categories_contra_account_id_foreign FOREIGN KEY (contra_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: expense_categories expense_categories_expense_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_categories
    ADD CONSTRAINT expense_categories_expense_account_id_foreign FOREIGN KEY (expense_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: expense_categories expense_categories_gen_prod_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_categories
    ADD CONSTRAINT expense_categories_gen_prod_posting_group_id_foreign FOREIGN KEY (gen_prod_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: expense_categories expense_categories_product_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_categories
    ADD CONSTRAINT expense_categories_product_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id);


--
-- Name: expense_categories expense_categories_vat_prod_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_categories
    ADD CONSTRAINT expense_categories_vat_prod_posting_group_id_foreign FOREIGN KEY (vat_prod_posting_group_id) REFERENCES public.vat_product_posting_groups(id);


--
-- Name: expense_transactions expense_transactions_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id);


--
-- Name: expense_transactions expense_transactions_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: expense_transactions expense_transactions_dimension_set_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_dimension_set_id_foreign FOREIGN KEY (dimension_set_id) REFERENCES public.dimension_sets(id);


--
-- Name: expense_transactions expense_transactions_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id);


--
-- Name: expense_transactions expense_transactions_expense_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_expense_account_id_foreign FOREIGN KEY (expense_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: expense_transactions expense_transactions_gen_bus_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_gen_bus_posting_group_id_foreign FOREIGN KEY (gen_bus_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: expense_transactions expense_transactions_gen_prod_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_gen_prod_posting_group_id_foreign FOREIGN KEY (gen_prod_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: expense_transactions expense_transactions_gl_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_gl_entry_id_foreign FOREIGN KEY (gl_entry_id) REFERENCES public.gl_entries(id);


--
-- Name: expense_transactions expense_transactions_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: expense_transactions expense_transactions_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id);


--
-- Name: expense_transactions expense_transactions_product_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_product_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id);


--
-- Name: expense_transactions expense_transactions_reversed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_reversed_by_foreign FOREIGN KEY (reversed_by) REFERENCES public.expense_transactions(id);


--
-- Name: expense_transactions expense_transactions_vat_bus_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_vat_bus_posting_group_id_foreign FOREIGN KEY (vat_bus_posting_group_id) REFERENCES public.vat_business_posting_groups(id);


--
-- Name: expense_transactions expense_transactions_vat_prod_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_vat_prod_posting_group_id_foreign FOREIGN KEY (vat_prod_posting_group_id) REFERENCES public.vat_product_posting_groups(id);


--
-- Name: expense_transactions expense_transactions_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_transactions
    ADD CONSTRAINT expense_transactions_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: fa_classes fa_classes_default_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_classes
    ADD CONSTRAINT fa_classes_default_posting_group_id_foreign FOREIGN KEY (default_posting_group_id) REFERENCES public.fa_posting_groups(id);


--
-- Name: fa_insurance_policies fa_insurance_policies_fixed_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_insurance_policies
    ADD CONSTRAINT fa_insurance_policies_fixed_asset_id_foreign FOREIGN KEY (fixed_asset_id) REFERENCES public.fixed_assets(id) ON DELETE CASCADE;


--
-- Name: fa_insurance_policies fa_insurance_policies_insurance_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_insurance_policies
    ADD CONSTRAINT fa_insurance_policies_insurance_vendor_id_foreign FOREIGN KEY (insurance_vendor_id) REFERENCES public.vendors(id);


--
-- Name: fa_journal_batches fa_journal_batches_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_batches
    ADD CONSTRAINT fa_journal_batches_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: fa_journal_batches fa_journal_batches_depreciation_book_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_batches
    ADD CONSTRAINT fa_journal_batches_depreciation_book_id_foreign FOREIGN KEY (depreciation_book_id) REFERENCES public.depreciation_books(id);


--
-- Name: fa_journal_batches fa_journal_batches_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_batches
    ADD CONSTRAINT fa_journal_batches_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.fa_journal_templates(id) ON DELETE CASCADE;


--
-- Name: fa_journal_lines fa_journal_lines_batch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_lines
    ADD CONSTRAINT fa_journal_lines_batch_id_foreign FOREIGN KEY (batch_id) REFERENCES public.fa_journal_batches(id) ON DELETE CASCADE;


--
-- Name: fa_journal_lines fa_journal_lines_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_lines
    ADD CONSTRAINT fa_journal_lines_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: fa_journal_lines fa_journal_lines_fa_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_lines
    ADD CONSTRAINT fa_journal_lines_fa_posting_group_id_foreign FOREIGN KEY (fa_posting_group_id) REFERENCES public.fa_posting_groups(id);


--
-- Name: fa_journal_lines fa_journal_lines_fixed_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_lines
    ADD CONSTRAINT fa_journal_lines_fixed_asset_id_foreign FOREIGN KEY (fixed_asset_id) REFERENCES public.fixed_assets(id);


--
-- Name: fa_journal_lines fa_journal_lines_override_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_lines
    ADD CONSTRAINT fa_journal_lines_override_account_id_foreign FOREIGN KEY (override_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_journal_templates fa_journal_templates_default_depreciation_book_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_templates
    ADD CONSTRAINT fa_journal_templates_default_depreciation_book_id_foreign FOREIGN KEY (default_depreciation_book_id) REFERENCES public.depreciation_books(id);


--
-- Name: fa_journal_templates fa_journal_templates_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_templates
    ADD CONSTRAINT fa_journal_templates_number_series_id_foreign FOREIGN KEY (number_series_id) REFERENCES public.number_series(id);


--
-- Name: fa_journal_templates fa_journal_templates_posting_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_journal_templates
    ADD CONSTRAINT fa_journal_templates_posting_number_series_id_foreign FOREIGN KEY (posting_number_series_id) REFERENCES public.number_series(id);


--
-- Name: fa_ledger_entries fa_ledger_entries_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_ledger_entries
    ADD CONSTRAINT fa_ledger_entries_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: fa_ledger_entries fa_ledger_entries_depreciation_book_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_ledger_entries
    ADD CONSTRAINT fa_ledger_entries_depreciation_book_id_foreign FOREIGN KEY (depreciation_book_id) REFERENCES public.depreciation_books(id);


--
-- Name: fa_ledger_entries fa_ledger_entries_fixed_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_ledger_entries
    ADD CONSTRAINT fa_ledger_entries_fixed_asset_id_foreign FOREIGN KEY (fixed_asset_id) REFERENCES public.fixed_assets(id);


--
-- Name: fa_ledger_entries fa_ledger_entries_gl_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_ledger_entries
    ADD CONSTRAINT fa_ledger_entries_gl_entry_id_foreign FOREIGN KEY (gl_entry_id) REFERENCES public.gl_entries(id);


--
-- Name: fa_ledger_entries fa_ledger_entries_reversed_entry_fixed_asset_id_reversed_entry_; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_ledger_entries
    ADD CONSTRAINT fa_ledger_entries_reversed_entry_fixed_asset_id_reversed_entry_ FOREIGN KEY (reversed_entry_fixed_asset_id, reversed_entry_depreciation_book_id, reversed_entry_no) REFERENCES public.fa_ledger_entries(fixed_asset_id, depreciation_book_id, entry_no) ON DELETE SET NULL;


--
-- Name: fa_locations fa_locations_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_locations
    ADD CONSTRAINT fa_locations_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: fa_locations fa_locations_responsible_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_locations
    ADD CONSTRAINT fa_locations_responsible_employee_id_foreign FOREIGN KEY (responsible_employee_id) REFERENCES public.users(id);


--
-- Name: fa_maintenance_logs fa_maintenance_logs_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_maintenance_logs
    ADD CONSTRAINT fa_maintenance_logs_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: fa_maintenance_logs fa_maintenance_logs_fixed_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_maintenance_logs
    ADD CONSTRAINT fa_maintenance_logs_fixed_asset_id_foreign FOREIGN KEY (fixed_asset_id) REFERENCES public.fixed_assets(id) ON DELETE CASCADE;


--
-- Name: fa_maintenance_logs fa_maintenance_logs_maintenance_contract_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_maintenance_logs
    ADD CONSTRAINT fa_maintenance_logs_maintenance_contract_id_foreign FOREIGN KEY (maintenance_contract_id) REFERENCES public.maintenance_contracts(id);


--
-- Name: fa_maintenance_logs fa_maintenance_logs_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_maintenance_logs
    ADD CONSTRAINT fa_maintenance_logs_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: fa_posting_groups fa_posting_groups_accumulated_depreciation_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_accumulated_depreciation_account_id_foreign FOREIGN KEY (accumulated_depreciation_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_acquisition_cost_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_acquisition_cost_account_id_foreign FOREIGN KEY (acquisition_cost_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_acquisition_cost_account_id_lcy_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_acquisition_cost_account_id_lcy_foreign FOREIGN KEY (acquisition_cost_account_id_lcy) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_appreciation_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_appreciation_account_id_foreign FOREIGN KEY (appreciation_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_capitalization_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_capitalization_account_id_foreign FOREIGN KEY (capitalization_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_deferred_tax_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_deferred_tax_account_id_foreign FOREIGN KEY (deferred_tax_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_depreciation_expense_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_depreciation_expense_account_id_foreign FOREIGN KEY (depreciation_expense_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_disposal_gain_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_disposal_gain_account_id_foreign FOREIGN KEY (disposal_gain_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_disposal_loss_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_disposal_loss_account_id_foreign FOREIGN KEY (disposal_loss_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_disposal_proceeds_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_disposal_proceeds_account_id_foreign FOREIGN KEY (disposal_proceeds_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_maintenance_expense_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_maintenance_expense_account_id_foreign FOREIGN KEY (maintenance_expense_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_revaluation_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_revaluation_account_id_foreign FOREIGN KEY (revaluation_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_revaluation_gain_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_revaluation_gain_account_id_foreign FOREIGN KEY (revaluation_gain_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_reversal_of_revaluation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_reversal_of_revaluation_id_foreign FOREIGN KEY (reversal_of_revaluation_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_posting_groups fa_posting_groups_tax_depreciation_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_posting_groups
    ADD CONSTRAINT fa_posting_groups_tax_depreciation_account_id_foreign FOREIGN KEY (tax_depreciation_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: fa_subclasses fa_subclasses_default_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_subclasses
    ADD CONSTRAINT fa_subclasses_default_posting_group_id_foreign FOREIGN KEY (default_posting_group_id) REFERENCES public.fa_posting_groups(id);


--
-- Name: fa_subclasses fa_subclasses_fa_class_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa_subclasses
    ADD CONSTRAINT fa_subclasses_fa_class_id_foreign FOREIGN KEY (fa_class_id) REFERENCES public.fa_classes(id) ON DELETE CASCADE;


--
-- Name: factories factories_business_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.factories
    ADD CONSTRAINT factories_business_id_foreign FOREIGN KEY (business_id) REFERENCES public.businesses(id) ON DELETE CASCADE;


--
-- Name: fiscal_reopen_logs fiscal_reopen_logs_requested_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fiscal_reopen_logs
    ADD CONSTRAINT fiscal_reopen_logs_requested_by_foreign FOREIGN KEY (requested_by) REFERENCES public.users(id);


--
-- Name: fixed_asset_journal_batches fixed_asset_journal_batches_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_batches
    ADD CONSTRAINT fixed_asset_journal_batches_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.fixed_asset_journal_templates(id) ON DELETE CASCADE;


--
-- Name: fixed_asset_journal_lines fixed_asset_journal_lines_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_lines
    ADD CONSTRAINT fixed_asset_journal_lines_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);


--
-- Name: fixed_asset_journal_lines fixed_asset_journal_lines_batch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_lines
    ADD CONSTRAINT fixed_asset_journal_lines_batch_id_foreign FOREIGN KEY (batch_id) REFERENCES public.fixed_asset_journal_batches(id) ON DELETE CASCADE;


--
-- Name: fixed_asset_journal_lines fixed_asset_journal_lines_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_lines
    ADD CONSTRAINT fixed_asset_journal_lines_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: fixed_asset_journal_templates fixed_asset_journal_templates_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_asset_journal_templates
    ADD CONSTRAINT fixed_asset_journal_templates_number_series_id_foreign FOREIGN KEY (number_series_id) REFERENCES public.number_series(id);


--
-- Name: fixed_assets fixed_assets_acquisition_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_acquisition_vendor_id_foreign FOREIGN KEY (acquisition_vendor_id) REFERENCES public.vendors(id);


--
-- Name: fixed_assets fixed_assets_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: fixed_assets fixed_assets_depreciation_book_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_depreciation_book_id_foreign FOREIGN KEY (depreciation_book_id) REFERENCES public.depreciation_books(id);


--
-- Name: fixed_assets fixed_assets_fa_class_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_fa_class_id_foreign FOREIGN KEY (fa_class_id) REFERENCES public.fa_classes(id) ON DELETE SET NULL;


--
-- Name: fixed_assets fixed_assets_fa_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_fa_location_id_foreign FOREIGN KEY (fa_location_id) REFERENCES public.fa_locations(id) ON DELETE SET NULL;


--
-- Name: fixed_assets fixed_assets_fa_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_fa_posting_group_id_foreign FOREIGN KEY (fa_posting_group_id) REFERENCES public.fa_posting_groups(id);


--
-- Name: fixed_assets fixed_assets_fa_subclass_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_fa_subclass_id_foreign FOREIGN KEY (fa_subclass_id) REFERENCES public.fa_subclasses(id) ON DELETE SET NULL;


--
-- Name: fixed_assets fixed_assets_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: fixed_assets fixed_assets_main_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_main_vendor_id_foreign FOREIGN KEY (main_vendor_id) REFERENCES public.vendors(id);


--
-- Name: fixed_assets fixed_assets_modified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_modified_by_foreign FOREIGN KEY (modified_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: fixed_assets fixed_assets_responsible_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_responsible_employee_id_foreign FOREIGN KEY (responsible_employee_id) REFERENCES public.users(id);


--
-- Name: fixed_assets fixed_assets_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixed_assets
    ADD CONSTRAINT fixed_assets_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: general_business_posting_groups general_business_posting_groups_default_vat_business_posting_gr; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_business_posting_groups
    ADD CONSTRAINT general_business_posting_groups_default_vat_business_posting_gr FOREIGN KEY (default_vat_business_posting_group_id) REFERENCES public.vat_business_posting_groups(id) ON DELETE SET NULL;


--
-- Name: general_journal_batches general_journal_batches_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_batches
    ADD CONSTRAINT general_journal_batches_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: general_journal_batches general_journal_batches_balancing_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_batches
    ADD CONSTRAINT general_journal_batches_balancing_account_id_foreign FOREIGN KEY (balancing_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_journal_batches general_journal_batches_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_batches
    ADD CONSTRAINT general_journal_batches_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.general_journal_templates(id) ON DELETE CASCADE;


--
-- Name: general_journal_lines general_journal_lines_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_lines
    ADD CONSTRAINT general_journal_lines_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_journal_lines general_journal_lines_balancing_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_lines
    ADD CONSTRAINT general_journal_lines_balancing_account_id_foreign FOREIGN KEY (balancing_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_journal_lines general_journal_lines_batch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_lines
    ADD CONSTRAINT general_journal_lines_batch_id_foreign FOREIGN KEY (batch_id) REFERENCES public.general_journal_batches(id) ON DELETE CASCADE;


--
-- Name: general_journal_lines general_journal_lines_business_unit_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_lines
    ADD CONSTRAINT general_journal_lines_business_unit_id_foreign FOREIGN KEY (business_unit_id) REFERENCES public.business_units(id);


--
-- Name: general_journal_lines general_journal_lines_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_lines
    ADD CONSTRAINT general_journal_lines_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: general_journal_templates general_journal_templates_default_balancing_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_templates
    ADD CONSTRAINT general_journal_templates_default_balancing_account_id_foreign FOREIGN KEY (default_balancing_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_journal_templates general_journal_templates_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_templates
    ADD CONSTRAINT general_journal_templates_number_series_id_foreign FOREIGN KEY (number_series_id) REFERENCES public.number_series(id);


--
-- Name: general_journal_templates general_journal_templates_posting_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_journal_templates
    ADD CONSTRAINT general_journal_templates_posting_number_series_id_foreign FOREIGN KEY (posting_number_series_id) REFERENCES public.number_series(id);


--
-- Name: general_ledger_setup general_ledger_setup_default_expense_offset_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_ledger_setup
    ADD CONSTRAINT general_ledger_setup_default_expense_offset_account_id_foreign FOREIGN KEY (default_expense_offset_account_id) REFERENCES public.chart_of_accounts(id) ON DELETE SET NULL;


--
-- Name: general_ledger_setup general_ledger_setup_retained_earnings_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_ledger_setup
    ADD CONSTRAINT general_ledger_setup_retained_earnings_account_id_foreign FOREIGN KEY (retained_earnings_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setup_lines general_posting_setup_lines_chart_of_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setup_lines
    ADD CONSTRAINT general_posting_setup_lines_chart_of_account_id_foreign FOREIGN KEY (chart_of_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setup_lines general_posting_setup_lines_general_posting_setup_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setup_lines
    ADD CONSTRAINT general_posting_setup_lines_general_posting_setup_id_foreign FOREIGN KEY (general_posting_setup_id) REFERENCES public.general_posting_setups(id) ON DELETE CASCADE;


--
-- Name: general_posting_setups general_posting_setups_capacity_overhead_variance_account_id_fo; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_capacity_overhead_variance_account_id_fo FOREIGN KEY (capacity_overhead_variance_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_capacity_variance_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_capacity_variance_account_id_foreign FOREIGN KEY (capacity_variance_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_cogs_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_cogs_account_id_foreign FOREIGN KEY (cogs_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_cogs_credit_memo_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_cogs_credit_memo_account_id_foreign FOREIGN KEY (cogs_credit_memo_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_cogs_prepayment_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_cogs_prepayment_account_id_foreign FOREIGN KEY (cogs_prepayment_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_direct_cost_applied_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_direct_cost_applied_account_id_foreign FOREIGN KEY (direct_cost_applied_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_general_business_posting_group_id_foreig; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_general_business_posting_group_id_foreig FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: general_posting_setups general_posting_setups_general_product_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_general_product_posting_group_id_foreign FOREIGN KEY (general_product_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: general_posting_setups general_posting_setups_inventory_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_inventory_account_id_foreign FOREIGN KEY (inventory_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_inventory_adj_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_inventory_adj_account_id_foreign FOREIGN KEY (inventory_adj_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_manufacturing_overhead_variance_account_; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_manufacturing_overhead_variance_account_ FOREIGN KEY (manufacturing_overhead_variance_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_material_variance_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_material_variance_account_id_foreign FOREIGN KEY (material_variance_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_overhead_applied_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_overhead_applied_account_id_foreign FOREIGN KEY (overhead_applied_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_purchase_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_purchase_account_id_foreign FOREIGN KEY (purchase_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_purchase_credit_memo_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_purchase_credit_memo_account_id_foreign FOREIGN KEY (purchase_credit_memo_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_purchase_variance_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_purchase_variance_account_id_foreign FOREIGN KEY (purchase_variance_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_sales_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_sales_account_id_foreign FOREIGN KEY (sales_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_sales_credit_memo_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_sales_credit_memo_account_id_foreign FOREIGN KEY (sales_credit_memo_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_posting_setups general_posting_setups_sales_prepayment_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_posting_setups
    ADD CONSTRAINT general_posting_setups_sales_prepayment_account_id_foreign FOREIGN KEY (sales_prepayment_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: general_product_posting_groups general_product_posting_groups_default_vat_product_posting_grou; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.general_product_posting_groups
    ADD CONSTRAINT general_product_posting_groups_default_vat_product_posting_grou FOREIGN KEY (default_vat_product_posting_group_id) REFERENCES public.vat_product_posting_groups(id) ON DELETE SET NULL;


--
-- Name: gl_accounts gl_accounts_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gl_accounts
    ADD CONSTRAINT gl_accounts_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: gl_accounts gl_accounts_last_modified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gl_accounts
    ADD CONSTRAINT gl_accounts_last_modified_by_foreign FOREIGN KEY (last_modified_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: gl_accounts gl_accounts_parent_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gl_accounts
    ADD CONSTRAINT gl_accounts_parent_account_id_foreign FOREIGN KEY (parent_account_id) REFERENCES public.gl_accounts(id) ON DELETE SET NULL;


--
-- Name: gl_entries gl_entries_chart_of_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gl_entries
    ADD CONSTRAINT gl_entries_chart_of_account_id_foreign FOREIGN KEY (chart_of_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: gl_entries gl_entries_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gl_entries
    ADD CONSTRAINT gl_entries_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id);


--
-- Name: inventory_adjustment_journals inventory_adjustment_journals_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustment_journals
    ADD CONSTRAINT inventory_adjustment_journals_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id);


--
-- Name: inventory_adjustment_journals inventory_adjustment_journals_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustment_journals
    ADD CONSTRAINT inventory_adjustment_journals_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id);


--
-- Name: inventory_adjustment_lines inventory_adjustment_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustment_lines
    ADD CONSTRAINT inventory_adjustment_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: inventory_adjustment_lines inventory_adjustment_lines_journal_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustment_lines
    ADD CONSTRAINT inventory_adjustment_lines_journal_id_foreign FOREIGN KEY (journal_id) REFERENCES public.inventory_adjustment_journals(id) ON DELETE CASCADE;


--
-- Name: inventory_posting_setups inventory_posting_setups_inventory_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_posting_setups
    ADD CONSTRAINT inventory_posting_setups_inventory_account_id_foreign FOREIGN KEY (inventory_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: inventory_posting_setups inventory_posting_setups_inventory_account_interim_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_posting_setups
    ADD CONSTRAINT inventory_posting_setups_inventory_account_interim_id_foreign FOREIGN KEY (inventory_account_interim_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: inventory_posting_setups inventory_posting_setups_inventory_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_posting_setups
    ADD CONSTRAINT inventory_posting_setups_inventory_posting_group_id_foreign FOREIGN KEY (inventory_posting_group_id) REFERENCES public.inventory_posting_groups(id);


--
-- Name: inventory_posting_setups inventory_posting_setups_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_posting_setups
    ADD CONSTRAINT inventory_posting_setups_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: inventory_posting_setups inventory_posting_setups_wip_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_posting_setups
    ADD CONSTRAINT inventory_posting_setups_wip_account_id_foreign FOREIGN KEY (wip_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: inventory_putaway_lines inventory_putaway_lines_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_putaway_lines
    ADD CONSTRAINT inventory_putaway_lines_bin_id_foreign FOREIGN KEY (bin_id) REFERENCES public.bins(id);


--
-- Name: inventory_putaway_lines inventory_putaway_lines_inventory_putaway_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_putaway_lines
    ADD CONSTRAINT inventory_putaway_lines_inventory_putaway_id_foreign FOREIGN KEY (inventory_putaway_id) REFERENCES public.inventory_putaways(id) ON DELETE CASCADE;


--
-- Name: inventory_putaway_lines inventory_putaway_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_putaway_lines
    ADD CONSTRAINT inventory_putaway_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: inventory_putaways inventory_putaways_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_putaways
    ADD CONSTRAINT inventory_putaways_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id);


--
-- Name: inventory_putaways inventory_putaways_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_putaways
    ADD CONSTRAINT inventory_putaways_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: item_category_assignments item_category_assignments_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_category_assignments
    ADD CONSTRAINT item_category_assignments_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- Name: item_category_assignments item_category_assignments_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_category_assignments
    ADD CONSTRAINT item_category_assignments_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id) ON DELETE CASCADE;


--
-- Name: item_charges item_charges_gen_prod_posting_group_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_charges
    ADD CONSTRAINT item_charges_gen_prod_posting_group_foreign FOREIGN KEY (gen_prod_posting_group) REFERENCES public.general_product_posting_groups(code) ON DELETE SET NULL;


--
-- Name: item_charges item_charges_vat_prod_posting_group_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_charges
    ADD CONSTRAINT item_charges_vat_prod_posting_group_foreign FOREIGN KEY (vat_prod_posting_group) REFERENCES public.vat_product_posting_groups(code) ON DELETE SET NULL;


--
-- Name: item_journal_batches item_journal_batches_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_batches
    ADD CONSTRAINT item_journal_batches_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: item_journal_batches item_journal_batches_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_batches
    ADD CONSTRAINT item_journal_batches_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: item_journal_batches item_journal_batches_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_batches
    ADD CONSTRAINT item_journal_batches_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.item_journal_templates(id) ON DELETE CASCADE;


--
-- Name: item_journal_lines item_journal_lines_batch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_batch_id_foreign FOREIGN KEY (batch_id) REFERENCES public.item_journal_batches(id) ON DELETE CASCADE;


--
-- Name: item_journal_lines item_journal_lines_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_bin_id_foreign FOREIGN KEY (bin_id) REFERENCES public.bins(id);


--
-- Name: item_journal_lines item_journal_lines_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: item_journal_lines item_journal_lines_gen_bus_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_gen_bus_posting_group_id_foreign FOREIGN KEY (gen_bus_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: item_journal_lines item_journal_lines_inventory_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_inventory_posting_group_id_foreign FOREIGN KEY (inventory_posting_group_id) REFERENCES public.inventory_posting_groups(id);


--
-- Name: item_journal_lines item_journal_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: item_journal_lines item_journal_lines_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: item_journal_lines item_journal_lines_new_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_new_bin_id_foreign FOREIGN KEY (new_bin_id) REFERENCES public.bins(id);


--
-- Name: item_journal_lines item_journal_lines_new_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_new_location_id_foreign FOREIGN KEY (new_location_id) REFERENCES public.locations(id);


--
-- Name: item_journal_lines item_journal_lines_new_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_new_zone_id_foreign FOREIGN KEY (new_zone_id) REFERENCES public.zones(id);


--
-- Name: item_journal_lines item_journal_lines_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_lines
    ADD CONSTRAINT item_journal_lines_zone_id_foreign FOREIGN KEY (zone_id) REFERENCES public.zones(id);


--
-- Name: item_journal_templates item_journal_templates_default_inventory_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_templates
    ADD CONSTRAINT item_journal_templates_default_inventory_account_id_foreign FOREIGN KEY (default_inventory_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: item_journal_templates item_journal_templates_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_templates
    ADD CONSTRAINT item_journal_templates_number_series_id_foreign FOREIGN KEY (number_series_id) REFERENCES public.number_series(id);


--
-- Name: item_journal_templates item_journal_templates_posting_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_journal_templates
    ADD CONSTRAINT item_journal_templates_posting_number_series_id_foreign FOREIGN KEY (posting_number_series_id) REFERENCES public.number_series(id);


--
-- Name: item_ledger_entries item_ledger_entries_general_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_ledger_entries
    ADD CONSTRAINT item_ledger_entries_general_business_posting_group_id_foreign FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: item_ledger_entries item_ledger_entries_general_product_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_ledger_entries
    ADD CONSTRAINT item_ledger_entries_general_product_posting_group_id_foreign FOREIGN KEY (general_product_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: item_ledger_entries item_ledger_entries_inventory_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_ledger_entries
    ADD CONSTRAINT item_ledger_entries_inventory_posting_group_id_foreign FOREIGN KEY (inventory_posting_group_id) REFERENCES public.inventory_posting_groups(id);


--
-- Name: item_ledger_entries item_ledger_entries_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_ledger_entries
    ADD CONSTRAINT item_ledger_entries_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: item_ledger_entries item_ledger_entries_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_ledger_entries
    ADD CONSTRAINT item_ledger_entries_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: item_lots item_lots_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_lots
    ADD CONSTRAINT item_lots_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id) ON DELETE CASCADE;


--
-- Name: item_skus item_skus_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_skus
    ADD CONSTRAINT item_skus_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id) ON DELETE CASCADE;


--
-- Name: item_skus item_skus_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_skus
    ADD CONSTRAINT item_skus_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id) ON DELETE CASCADE;


--
-- Name: item_uom_assignments item_uom_assignments_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_uom_assignments
    ADD CONSTRAINT item_uom_assignments_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id) ON DELETE CASCADE;


--
-- Name: item_uom_assignments item_uom_assignments_uom_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item_uom_assignments
    ADD CONSTRAINT item_uom_assignments_uom_id_foreign FOREIGN KEY (uom_id) REFERENCES public.unit_of_measures(id) ON DELETE CASCADE;


--
-- Name: items items_base_uom_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_base_uom_id_foreign FOREIGN KEY (base_uom_id) REFERENCES public.unit_of_measures(id);


--
-- Name: items items_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id) ON DELETE SET NULL;


--
-- Name: items items_general_posting_setup_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_general_posting_setup_id_foreign FOREIGN KEY (general_posting_setup_id) REFERENCES public.general_posting_setups(id);


--
-- Name: items items_general_product_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_general_product_posting_group_id_foreign FOREIGN KEY (general_product_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: items items_inventory_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_inventory_bin_id_foreign FOREIGN KEY (inventory_bin_id) REFERENCES public.bins(id) ON DELETE SET NULL;


--
-- Name: items items_inventory_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_inventory_posting_group_id_foreign FOREIGN KEY (inventory_posting_group_id) REFERENCES public.inventory_posting_groups(id);


--
-- Name: items items_inventory_posting_setup_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_inventory_posting_setup_id_foreign FOREIGN KEY (inventory_posting_setup_id) REFERENCES public.inventory_posting_setups(id);


--
-- Name: items items_item_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_item_category_id_foreign FOREIGN KEY (item_category_id) REFERENCES public.categories(id) ON DELETE SET NULL;


--
-- Name: items items_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: items items_production_bom_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_production_bom_id_foreign FOREIGN KEY (production_bom_id) REFERENCES public.production_boms(id) ON DELETE SET NULL;


--
-- Name: items items_routing_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_routing_id_foreign FOREIGN KEY (routing_id) REFERENCES public.routings(id) ON DELETE SET NULL;


--
-- Name: items items_uom_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_uom_id_foreign FOREIGN KEY (uom_id) REFERENCES public.unit_of_measures(id);


--
-- Name: items items_vat_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_vat_id_foreign FOREIGN KEY (vat_id) REFERENCES public.vat_masters(id);


--
-- Name: items items_vat_product_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items
    ADD CONSTRAINT items_vat_product_posting_group_id_foreign FOREIGN KEY (vat_product_posting_group_id) REFERENCES public.vat_product_posting_groups(id);


--
-- Name: job_journal_lines job_journal_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_journal_lines
    ADD CONSTRAINT job_journal_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: job_journal_lines job_journal_lines_job_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_journal_lines
    ADD CONSTRAINT job_journal_lines_job_id_foreign FOREIGN KEY (job_id) REFERENCES public.jobs(id);


--
-- Name: job_journal_lines job_journal_lines_journal_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_journal_lines
    ADD CONSTRAINT job_journal_lines_journal_line_id_foreign FOREIGN KEY (journal_line_id) REFERENCES public.journal_lines(id) ON DELETE CASCADE;


--
-- Name: job_journal_lines job_journal_lines_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_journal_lines
    ADD CONSTRAINT job_journal_lines_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: journal_batches journal_batches_journal_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_batches
    ADD CONSTRAINT journal_batches_journal_template_id_foreign FOREIGN KEY (journal_template_id) REFERENCES public.journal_templates(id) ON DELETE CASCADE;


--
-- Name: journal_batches journal_batches_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_batches
    ADD CONSTRAINT journal_batches_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: journal_lines journal_lines_journal_batch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_lines
    ADD CONSTRAINT journal_lines_journal_batch_id_foreign FOREIGN KEY (journal_batch_id) REFERENCES public.journal_batches(id) ON DELETE CASCADE;


--
-- Name: locations locations_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.locations(id) ON DELETE SET NULL;


--
-- Name: machine_centers machine_centers_fixed_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.machine_centers
    ADD CONSTRAINT machine_centers_fixed_asset_id_foreign FOREIGN KEY (fixed_asset_id) REFERENCES public.fixed_assets(id) ON DELETE SET NULL;


--
-- Name: machine_centers machine_centers_operator_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.machine_centers
    ADD CONSTRAINT machine_centers_operator_employee_id_foreign FOREIGN KEY (operator_employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: machine_centers machine_centers_work_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.machine_centers
    ADD CONSTRAINT machine_centers_work_center_id_foreign FOREIGN KEY (work_center_id) REFERENCES public.work_centers(id);


--
-- Name: maintenance_contract_assets maintenance_contract_assets_fixed_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_assets
    ADD CONSTRAINT maintenance_contract_assets_fixed_asset_id_foreign FOREIGN KEY (fixed_asset_id) REFERENCES public.fixed_assets(id) ON DELETE CASCADE;


--
-- Name: maintenance_contract_assets maintenance_contract_assets_maintenance_contract_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_assets
    ADD CONSTRAINT maintenance_contract_assets_maintenance_contract_id_foreign FOREIGN KEY (maintenance_contract_id) REFERENCES public.maintenance_contracts(id) ON DELETE CASCADE;


--
-- Name: maintenance_contract_billings maintenance_contract_billings_maintenance_contract_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_billings
    ADD CONSTRAINT maintenance_contract_billings_maintenance_contract_id_foreign FOREIGN KEY (maintenance_contract_id) REFERENCES public.maintenance_contracts(id) ON DELETE CASCADE;


--
-- Name: maintenance_contract_billings maintenance_contract_billings_purchase_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_billings
    ADD CONSTRAINT maintenance_contract_billings_purchase_invoice_id_foreign FOREIGN KEY (purchase_invoice_id) REFERENCES public.purchase_invoices(id) ON DELETE SET NULL;


--
-- Name: maintenance_contract_schedules maintenance_contract_schedules_fixed_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_schedules
    ADD CONSTRAINT maintenance_contract_schedules_fixed_asset_id_foreign FOREIGN KEY (fixed_asset_id) REFERENCES public.fixed_assets(id) ON DELETE SET NULL;


--
-- Name: maintenance_contract_schedules maintenance_contract_schedules_maintenance_contract_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contract_schedules
    ADD CONSTRAINT maintenance_contract_schedules_maintenance_contract_id_foreign FOREIGN KEY (maintenance_contract_id) REFERENCES public.maintenance_contracts(id) ON DELETE CASCADE;


--
-- Name: maintenance_contracts maintenance_contracts_accrual_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts
    ADD CONSTRAINT maintenance_contracts_accrual_account_id_foreign FOREIGN KEY (accrual_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: maintenance_contracts maintenance_contracts_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts
    ADD CONSTRAINT maintenance_contracts_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id);


--
-- Name: maintenance_contracts maintenance_contracts_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts
    ADD CONSTRAINT maintenance_contracts_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: maintenance_contracts maintenance_contracts_expense_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts
    ADD CONSTRAINT maintenance_contracts_expense_account_id_foreign FOREIGN KEY (expense_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: maintenance_contracts maintenance_contracts_fa_class_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts
    ADD CONSTRAINT maintenance_contracts_fa_class_id_foreign FOREIGN KEY (fa_class_id) REFERENCES public.fa_classes(id);


--
-- Name: maintenance_contracts maintenance_contracts_fa_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts
    ADD CONSTRAINT maintenance_contracts_fa_location_id_foreign FOREIGN KEY (fa_location_id) REFERENCES public.fa_locations(id);


--
-- Name: maintenance_contracts maintenance_contracts_modified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts
    ADD CONSTRAINT maintenance_contracts_modified_by_foreign FOREIGN KEY (modified_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: maintenance_contracts maintenance_contracts_prepaid_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts
    ADD CONSTRAINT maintenance_contracts_prepaid_account_id_foreign FOREIGN KEY (prepaid_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: maintenance_contracts maintenance_contracts_responsible_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts
    ADD CONSTRAINT maintenance_contracts_responsible_employee_id_foreign FOREIGN KEY (responsible_employee_id) REFERENCES public.employees(id);


--
-- Name: maintenance_contracts maintenance_contracts_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_contracts
    ADD CONSTRAINT maintenance_contracts_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: number_series_lines number_series_lines_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.number_series_lines
    ADD CONSTRAINT number_series_lines_number_series_id_foreign FOREIGN KEY (number_series_id) REFERENCES public.number_series(id) ON DELETE CASCADE;


--
-- Name: pay_codes pay_codes_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pay_codes
    ADD CONSTRAINT pay_codes_gl_account_id_foreign FOREIGN KEY (gl_account_id) REFERENCES public.chart_of_accounts(id) ON DELETE CASCADE;


--
-- Name: payment_applications payment_applications_applied_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_applications
    ADD CONSTRAINT payment_applications_applied_by_foreign FOREIGN KEY (applied_by) REFERENCES public.users(id);


--
-- Name: payment_applications payment_applications_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_applications
    ADD CONSTRAINT payment_applications_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id) ON DELETE SET NULL;


--
-- Name: payment_applications payment_applications_payment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_applications
    ADD CONSTRAINT payment_applications_payment_id_foreign FOREIGN KEY (payment_id) REFERENCES public.payments(id) ON DELETE CASCADE;


--
-- Name: payment_applications payment_applications_reversed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_applications
    ADD CONSTRAINT payment_applications_reversed_by_foreign FOREIGN KEY (reversed_by) REFERENCES public.users(id);


--
-- Name: payment_journal_lines payment_journal_lines_bank_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_journal_lines
    ADD CONSTRAINT payment_journal_lines_bank_account_id_foreign FOREIGN KEY (bank_account_id) REFERENCES public.bank_accounts(id);


--
-- Name: payment_journal_lines payment_journal_lines_journal_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_journal_lines
    ADD CONSTRAINT payment_journal_lines_journal_line_id_foreign FOREIGN KEY (journal_line_id) REFERENCES public.journal_lines(id) ON DELETE CASCADE;


--
-- Name: payment_journal_lines payment_journal_lines_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_journal_lines
    ADD CONSTRAINT payment_journal_lines_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: payment_terms payment_terms_discount_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_terms
    ADD CONSTRAINT payment_terms_discount_account_id_foreign FOREIGN KEY (discount_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: payment_terms payment_terms_payment_tolerance_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payment_terms
    ADD CONSTRAINT payment_terms_payment_tolerance_account_id_foreign FOREIGN KEY (payment_tolerance_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: payments payments_bank_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_bank_account_id_foreign FOREIGN KEY (bank_account_id) REFERENCES public.bank_accounts(id);


--
-- Name: payments payments_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: payments payments_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id) ON DELETE SET NULL;


--
-- Name: payments payments_general_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_general_business_posting_group_id_foreign FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: payments payments_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id);


--
-- Name: payments payments_reconciled_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_reconciled_by_foreign FOREIGN KEY (reconciled_by) REFERENCES public.users(id);


--
-- Name: payments payments_voided_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_voided_by_foreign FOREIGN KEY (voided_by) REFERENCES public.users(id);


--
-- Name: payroll_documents payroll_documents_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_documents
    ADD CONSTRAINT payroll_documents_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.employees(id);


--
-- Name: payroll_documents payroll_documents_payroll_period_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_documents
    ADD CONSTRAINT payroll_documents_payroll_period_id_foreign FOREIGN KEY (payroll_period_id) REFERENCES public.payroll_periods(id);


--
-- Name: payroll_lines payroll_lines_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_lines
    ADD CONSTRAINT payroll_lines_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: payroll_lines payroll_lines_pay_code_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_lines
    ADD CONSTRAINT payroll_lines_pay_code_id_foreign FOREIGN KEY (pay_code_id) REFERENCES public.pay_codes(id) ON DELETE CASCADE;


--
-- Name: payroll_lines payroll_lines_payroll_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_lines
    ADD CONSTRAINT payroll_lines_payroll_document_id_foreign FOREIGN KEY (payroll_document_id) REFERENCES public.payroll_documents(id) ON DELETE CASCADE;


--
-- Name: payroll_posting_groups payroll_posting_groups_net_pay_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_posting_groups
    ADD CONSTRAINT payroll_posting_groups_net_pay_account_id_foreign FOREIGN KEY (net_pay_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: payroll_posting_groups payroll_posting_groups_salaries_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_posting_groups
    ADD CONSTRAINT payroll_posting_groups_salaries_account_id_foreign FOREIGN KEY (salaries_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: payroll_posting_groups payroll_posting_groups_social_security_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_posting_groups
    ADD CONSTRAINT payroll_posting_groups_social_security_account_id_foreign FOREIGN KEY (social_security_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: payroll_posting_groups payroll_posting_groups_tax_payable_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_posting_groups
    ADD CONSTRAINT payroll_posting_groups_tax_payable_account_id_foreign FOREIGN KEY (tax_payable_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: payroll_posting_groups payroll_posting_groups_wages_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_posting_groups
    ADD CONSTRAINT payroll_posting_groups_wages_account_id_foreign FOREIGN KEY (wages_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: physical_inventory_journals physical_inventory_journals_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.physical_inventory_journals
    ADD CONSTRAINT physical_inventory_journals_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id);


--
-- Name: physical_inventory_journals physical_inventory_journals_counted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.physical_inventory_journals
    ADD CONSTRAINT physical_inventory_journals_counted_by_foreign FOREIGN KEY (counted_by) REFERENCES public.users(id);


--
-- Name: physical_inventory_journals physical_inventory_journals_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.physical_inventory_journals
    ADD CONSTRAINT physical_inventory_journals_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id);


--
-- Name: physical_inventory_lines physical_inventory_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.physical_inventory_lines
    ADD CONSTRAINT physical_inventory_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: physical_inventory_lines physical_inventory_lines_journal_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.physical_inventory_lines
    ADD CONSTRAINT physical_inventory_lines_journal_id_foreign FOREIGN KEY (journal_id) REFERENCES public.physical_inventory_journals(id) ON DELETE CASCADE;


--
-- Name: posted_purchase_credit_memo_lines posted_purchase_credit_memo_lines_corrected_invoice_line_id_for; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memo_lines
    ADD CONSTRAINT posted_purchase_credit_memo_lines_corrected_invoice_line_id_for FOREIGN KEY (corrected_invoice_line_id) REFERENCES public.purchase_invoice_lines(id);


--
-- Name: posted_purchase_credit_memo_lines posted_purchase_credit_memo_lines_credit_memo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memo_lines
    ADD CONSTRAINT posted_purchase_credit_memo_lines_credit_memo_id_foreign FOREIGN KEY (credit_memo_id) REFERENCES public.posted_purchase_credit_memos(id) ON DELETE CASCADE;


--
-- Name: posted_purchase_credit_memo_lines posted_purchase_credit_memo_lines_general_product_posting_group; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memo_lines
    ADD CONSTRAINT posted_purchase_credit_memo_lines_general_product_posting_group FOREIGN KEY (general_product_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: posted_purchase_credit_memo_lines posted_purchase_credit_memo_lines_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memo_lines
    ADD CONSTRAINT posted_purchase_credit_memo_lines_gl_account_id_foreign FOREIGN KEY (gl_account_id) REFERENCES public.gl_accounts(id);


--
-- Name: posted_purchase_credit_memo_lines posted_purchase_credit_memo_lines_inventory_posting_group_id_fo; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memo_lines
    ADD CONSTRAINT posted_purchase_credit_memo_lines_inventory_posting_group_id_fo FOREIGN KEY (inventory_posting_group_id) REFERENCES public.inventory_posting_groups(id);


--
-- Name: posted_purchase_credit_memo_lines posted_purchase_credit_memo_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memo_lines
    ADD CONSTRAINT posted_purchase_credit_memo_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: posted_purchase_credit_memos posted_purchase_credit_memos_corrects_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memos
    ADD CONSTRAINT posted_purchase_credit_memos_corrects_invoice_id_foreign FOREIGN KEY (corrects_invoice_id) REFERENCES public.purchase_invoices(id);


--
-- Name: posted_purchase_credit_memos posted_purchase_credit_memos_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memos
    ADD CONSTRAINT posted_purchase_credit_memos_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id) ON DELETE SET NULL;


--
-- Name: posted_purchase_credit_memos posted_purchase_credit_memos_general_business_posting_group_id_; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memos
    ADD CONSTRAINT posted_purchase_credit_memos_general_business_posting_group_id_ FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: posted_purchase_credit_memos posted_purchase_credit_memos_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memos
    ADD CONSTRAINT posted_purchase_credit_memos_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id);


--
-- Name: posted_purchase_credit_memos posted_purchase_credit_memos_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memos
    ADD CONSTRAINT posted_purchase_credit_memos_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: posted_purchase_credit_memos posted_purchase_credit_memos_vendor_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_credit_memos
    ADD CONSTRAINT posted_purchase_credit_memos_vendor_posting_group_id_foreign FOREIGN KEY (vendor_posting_group_id) REFERENCES public.vendor_posting_groups(id);


--
-- Name: posted_purchase_invoice_lines posted_purchase_invoice_lines_general_product_posting_group_id_; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoice_lines
    ADD CONSTRAINT posted_purchase_invoice_lines_general_product_posting_group_id_ FOREIGN KEY (general_product_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: posted_purchase_invoice_lines posted_purchase_invoice_lines_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoice_lines
    ADD CONSTRAINT posted_purchase_invoice_lines_gl_account_id_foreign FOREIGN KEY (gl_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: posted_purchase_invoice_lines posted_purchase_invoice_lines_inventory_posting_group_id_foreig; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoice_lines
    ADD CONSTRAINT posted_purchase_invoice_lines_inventory_posting_group_id_foreig FOREIGN KEY (inventory_posting_group_id) REFERENCES public.inventory_posting_groups(id);


--
-- Name: posted_purchase_invoice_lines posted_purchase_invoice_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoice_lines
    ADD CONSTRAINT posted_purchase_invoice_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: posted_purchase_invoice_lines posted_purchase_invoice_lines_posted_purchase_invoice_id_foreig; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoice_lines
    ADD CONSTRAINT posted_purchase_invoice_lines_posted_purchase_invoice_id_foreig FOREIGN KEY (posted_purchase_invoice_id) REFERENCES public.posted_purchase_invoices(id) ON DELETE CASCADE;


--
-- Name: posted_purchase_invoices posted_purchase_invoices_cancelled_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoices
    ADD CONSTRAINT posted_purchase_invoices_cancelled_by_foreign FOREIGN KEY (cancelled_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: posted_purchase_invoices posted_purchase_invoices_general_business_posting_group_id_fore; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoices
    ADD CONSTRAINT posted_purchase_invoices_general_business_posting_group_id_fore FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: posted_purchase_invoices posted_purchase_invoices_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoices
    ADD CONSTRAINT posted_purchase_invoices_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: posted_purchase_invoices posted_purchase_invoices_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoices
    ADD CONSTRAINT posted_purchase_invoices_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: posted_purchase_invoices posted_purchase_invoices_vat_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoices
    ADD CONSTRAINT posted_purchase_invoices_vat_business_posting_group_id_foreign FOREIGN KEY (vat_business_posting_group_id) REFERENCES public.vat_business_posting_groups(id);


--
-- Name: posted_purchase_invoices posted_purchase_invoices_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoices
    ADD CONSTRAINT posted_purchase_invoices_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: posted_purchase_invoices posted_purchase_invoices_vendor_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_purchase_invoices
    ADD CONSTRAINT posted_purchase_invoices_vendor_posting_group_id_foreign FOREIGN KEY (vendor_posting_group_id) REFERENCES public.vendor_posting_groups(id);


--
-- Name: posted_sales_credit_memo_lines posted_sales_credit_memo_lines_cogs_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memo_lines
    ADD CONSTRAINT posted_sales_credit_memo_lines_cogs_account_id_foreign FOREIGN KEY (cogs_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: posted_sales_credit_memo_lines posted_sales_credit_memo_lines_general_product_posting_group_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memo_lines
    ADD CONSTRAINT posted_sales_credit_memo_lines_general_product_posting_group_id FOREIGN KEY (general_product_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: posted_sales_credit_memo_lines posted_sales_credit_memo_lines_inventory_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memo_lines
    ADD CONSTRAINT posted_sales_credit_memo_lines_inventory_account_id_foreign FOREIGN KEY (inventory_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: posted_sales_credit_memo_lines posted_sales_credit_memo_lines_inventory_posting_group_id_forei; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memo_lines
    ADD CONSTRAINT posted_sales_credit_memo_lines_inventory_posting_group_id_forei FOREIGN KEY (inventory_posting_group_id) REFERENCES public.inventory_posting_groups(id);


--
-- Name: posted_sales_credit_memo_lines posted_sales_credit_memo_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memo_lines
    ADD CONSTRAINT posted_sales_credit_memo_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: posted_sales_credit_memo_lines posted_sales_credit_memo_lines_posted_sales_credit_memo_id_fore; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memo_lines
    ADD CONSTRAINT posted_sales_credit_memo_lines_posted_sales_credit_memo_id_fore FOREIGN KEY (posted_sales_credit_memo_id) REFERENCES public.posted_sales_credit_memos(id) ON DELETE CASCADE;


--
-- Name: posted_sales_credit_memo_lines posted_sales_credit_memo_lines_returns_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memo_lines
    ADD CONSTRAINT posted_sales_credit_memo_lines_returns_account_id_foreign FOREIGN KEY (returns_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: posted_sales_credit_memo_lines posted_sales_credit_memo_lines_sales_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memo_lines
    ADD CONSTRAINT posted_sales_credit_memo_lines_sales_account_id_foreign FOREIGN KEY (sales_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: posted_sales_credit_memos posted_sales_credit_memos_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memos
    ADD CONSTRAINT posted_sales_credit_memos_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id) ON DELETE SET NULL;


--
-- Name: posted_sales_credit_memos posted_sales_credit_memos_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memos
    ADD CONSTRAINT posted_sales_credit_memos_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: posted_sales_credit_memos posted_sales_credit_memos_customer_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memos
    ADD CONSTRAINT posted_sales_credit_memos_customer_posting_group_id_foreign FOREIGN KEY (customer_posting_group_id) REFERENCES public.customer_posting_groups(id);


--
-- Name: posted_sales_credit_memos posted_sales_credit_memos_general_business_posting_group_id_for; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memos
    ADD CONSTRAINT posted_sales_credit_memos_general_business_posting_group_id_for FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: posted_sales_credit_memos posted_sales_credit_memos_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memos
    ADD CONSTRAINT posted_sales_credit_memos_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: posted_sales_credit_memos posted_sales_credit_memos_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memos
    ADD CONSTRAINT posted_sales_credit_memos_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id);


--
-- Name: posted_sales_credit_memos posted_sales_credit_memos_salesperson_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_credit_memos
    ADD CONSTRAINT posted_sales_credit_memos_salesperson_id_foreign FOREIGN KEY (salesperson_id) REFERENCES public.users(id);


--
-- Name: posted_sales_invoice_lines posted_sales_invoice_lines_cogs_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoice_lines
    ADD CONSTRAINT posted_sales_invoice_lines_cogs_account_id_foreign FOREIGN KEY (cogs_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: posted_sales_invoice_lines posted_sales_invoice_lines_general_product_posting_group_id_for; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoice_lines
    ADD CONSTRAINT posted_sales_invoice_lines_general_product_posting_group_id_for FOREIGN KEY (general_product_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: posted_sales_invoice_lines posted_sales_invoice_lines_inventory_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoice_lines
    ADD CONSTRAINT posted_sales_invoice_lines_inventory_account_id_foreign FOREIGN KEY (inventory_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: posted_sales_invoice_lines posted_sales_invoice_lines_inventory_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoice_lines
    ADD CONSTRAINT posted_sales_invoice_lines_inventory_posting_group_id_foreign FOREIGN KEY (inventory_posting_group_id) REFERENCES public.inventory_posting_groups(id);


--
-- Name: posted_sales_invoice_lines posted_sales_invoice_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoice_lines
    ADD CONSTRAINT posted_sales_invoice_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: posted_sales_invoice_lines posted_sales_invoice_lines_posted_sales_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoice_lines
    ADD CONSTRAINT posted_sales_invoice_lines_posted_sales_invoice_id_foreign FOREIGN KEY (posted_sales_invoice_id) REFERENCES public.posted_sales_invoices(id) ON DELETE CASCADE;


--
-- Name: posted_sales_invoice_lines posted_sales_invoice_lines_sales_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoice_lines
    ADD CONSTRAINT posted_sales_invoice_lines_sales_account_id_foreign FOREIGN KEY (sales_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: posted_sales_invoices posted_sales_invoices_cancelled_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoices
    ADD CONSTRAINT posted_sales_invoices_cancelled_by_foreign FOREIGN KEY (cancelled_by) REFERENCES public.users(id);


--
-- Name: posted_sales_invoices posted_sales_invoices_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoices
    ADD CONSTRAINT posted_sales_invoices_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id) ON DELETE SET NULL;


--
-- Name: posted_sales_invoices posted_sales_invoices_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoices
    ADD CONSTRAINT posted_sales_invoices_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: posted_sales_invoices posted_sales_invoices_customer_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoices
    ADD CONSTRAINT posted_sales_invoices_customer_posting_group_id_foreign FOREIGN KEY (customer_posting_group_id) REFERENCES public.customer_posting_groups(id);


--
-- Name: posted_sales_invoices posted_sales_invoices_general_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoices
    ADD CONSTRAINT posted_sales_invoices_general_business_posting_group_id_foreign FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: posted_sales_invoices posted_sales_invoices_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoices
    ADD CONSTRAINT posted_sales_invoices_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: posted_sales_invoices posted_sales_invoices_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoices
    ADD CONSTRAINT posted_sales_invoices_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id);


--
-- Name: posted_sales_invoices posted_sales_invoices_salesperson_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posted_sales_invoices
    ADD CONSTRAINT posted_sales_invoices_salesperson_id_foreign FOREIGN KEY (salesperson_id) REFERENCES public.users(id);


--
-- Name: price_change_template_lines price_change_template_lines_business_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.price_change_template_lines
    ADD CONSTRAINT price_change_template_lines_business_id_foreign FOREIGN KEY (business_id) REFERENCES public.businesses(id) ON DELETE SET NULL;


--
-- Name: price_change_template_lines price_change_template_lines_customer_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.price_change_template_lines
    ADD CONSTRAINT price_change_template_lines_customer_group_id_foreign FOREIGN KEY (customer_group_id) REFERENCES public.customer_groups(id) ON DELETE SET NULL;


--
-- Name: price_lists price_lists_customer_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.price_lists
    ADD CONSTRAINT price_lists_customer_group_id_foreign FOREIGN KEY (customer_group_id) REFERENCES public.customer_groups(id) ON DELETE SET NULL;


--
-- Name: price_lists price_lists_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.price_lists
    ADD CONSTRAINT price_lists_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: price_lists price_lists_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.price_lists
    ADD CONSTRAINT price_lists_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id) ON DELETE CASCADE;


--
-- Name: pricing_groups pricing_groups_general_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_groups
    ADD CONSTRAINT pricing_groups_general_business_posting_group_id_foreign FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: pricing_master pricing_master_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_master
    ADD CONSTRAINT pricing_master_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id);


--
-- Name: pricing_master pricing_master_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_master
    ADD CONSTRAINT pricing_master_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: pricing_master pricing_master_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_master
    ADD CONSTRAINT pricing_master_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: pricing_master pricing_master_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_master
    ADD CONSTRAINT pricing_master_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: pricing_master pricing_master_pricing_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_master
    ADD CONSTRAINT pricing_master_pricing_group_id_foreign FOREIGN KEY (pricing_group_id) REFERENCES public.pricing_groups(id);


--
-- Name: pricing_master_quantity_breaks pricing_master_quantity_breaks_pricing_master_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pricing_master_quantity_breaks
    ADD CONSTRAINT pricing_master_quantity_breaks_pricing_master_id_foreign FOREIGN KEY (pricing_master_id) REFERENCES public.pricing_master(id) ON DELETE CASCADE;


--
-- Name: production_bom_lines production_bom_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_lines
    ADD CONSTRAINT production_bom_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: production_bom_lines production_bom_lines_production_bom_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_lines
    ADD CONSTRAINT production_bom_lines_production_bom_id_foreign FOREIGN KEY (production_bom_id) REFERENCES public.production_boms(id) ON DELETE CASCADE;


--
-- Name: production_bom_lines production_bom_lines_production_bom_id_related_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_lines
    ADD CONSTRAINT production_bom_lines_production_bom_id_related_foreign FOREIGN KEY (production_bom_id_related) REFERENCES public.production_boms(id);


--
-- Name: production_bom_version_lines production_bom_version_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_version_lines
    ADD CONSTRAINT production_bom_version_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id) ON DELETE SET NULL;


--
-- Name: production_bom_version_lines production_bom_version_lines_production_bom_id_related_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_version_lines
    ADD CONSTRAINT production_bom_version_lines_production_bom_id_related_foreign FOREIGN KEY (production_bom_id_related) REFERENCES public.production_boms(id) ON DELETE SET NULL;


--
-- Name: production_bom_version_lines production_bom_version_lines_production_bom_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_version_lines
    ADD CONSTRAINT production_bom_version_lines_production_bom_version_id_foreign FOREIGN KEY (production_bom_version_id) REFERENCES public.production_bom_versions(id) ON DELETE CASCADE;


--
-- Name: production_bom_versions production_bom_versions_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_versions
    ADD CONSTRAINT production_bom_versions_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: production_bom_versions production_bom_versions_last_modified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_versions
    ADD CONSTRAINT production_bom_versions_last_modified_by_foreign FOREIGN KEY (last_modified_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: production_bom_versions production_bom_versions_production_bom_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_bom_versions
    ADD CONSTRAINT production_bom_versions_production_bom_id_foreign FOREIGN KEY (production_bom_id) REFERENCES public.production_boms(id) ON DELETE CASCADE;


--
-- Name: production_boms production_boms_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_boms
    ADD CONSTRAINT production_boms_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: production_boms production_boms_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_boms
    ADD CONSTRAINT production_boms_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: production_boms production_boms_last_modified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_boms
    ADD CONSTRAINT production_boms_last_modified_by_foreign FOREIGN KEY (last_modified_by) REFERENCES public.users(id);


--
-- Name: production_journal_batches production_journal_batches_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_batches
    ADD CONSTRAINT production_journal_batches_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: production_journal_batches production_journal_batches_production_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_batches
    ADD CONSTRAINT production_journal_batches_production_order_id_foreign FOREIGN KEY (production_order_id) REFERENCES public.production_orders(id);


--
-- Name: production_journal_batches production_journal_batches_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_batches
    ADD CONSTRAINT production_journal_batches_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.production_journal_templates(id) ON DELETE CASCADE;


--
-- Name: production_journal_lines production_journal_lines_batch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_batch_id_foreign FOREIGN KEY (batch_id) REFERENCES public.production_journal_batches(id) ON DELETE CASCADE;


--
-- Name: production_journal_lines production_journal_lines_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_bin_id_foreign FOREIGN KEY (bin_id) REFERENCES public.bins(id);


--
-- Name: production_journal_lines production_journal_lines_capacity_ledger_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_capacity_ledger_entry_id_foreign FOREIGN KEY (capacity_ledger_entry_id) REFERENCES public.capacity_ledger_entries(id);


--
-- Name: production_journal_lines production_journal_lines_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: production_journal_lines production_journal_lines_direct_cost_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_direct_cost_account_id_foreign FOREIGN KEY (direct_cost_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: production_journal_lines production_journal_lines_inventory_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_inventory_account_id_foreign FOREIGN KEY (inventory_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: production_journal_lines production_journal_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: production_journal_lines production_journal_lines_item_ledger_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_item_ledger_entry_id_foreign FOREIGN KEY (item_ledger_entry_id) REFERENCES public.item_ledger_entries(id);


--
-- Name: production_journal_lines production_journal_lines_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: production_journal_lines production_journal_lines_machine_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_machine_center_id_foreign FOREIGN KEY (machine_center_id) REFERENCES public.machine_centers(id);


--
-- Name: production_journal_lines production_journal_lines_output_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_output_bin_id_foreign FOREIGN KEY (output_bin_id) REFERENCES public.bins(id);


--
-- Name: production_journal_lines production_journal_lines_output_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_output_location_id_foreign FOREIGN KEY (output_location_id) REFERENCES public.locations(id);


--
-- Name: production_journal_lines production_journal_lines_overhead_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_overhead_account_id_foreign FOREIGN KEY (overhead_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: production_journal_lines production_journal_lines_production_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_production_order_id_foreign FOREIGN KEY (production_order_id) REFERENCES public.production_orders(id);


--
-- Name: production_journal_lines production_journal_lines_routing_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_routing_line_id_foreign FOREIGN KEY (routing_line_id) REFERENCES public.production_order_routing_lines(id);


--
-- Name: production_journal_lines production_journal_lines_wip_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_wip_account_id_foreign FOREIGN KEY (wip_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: production_journal_lines production_journal_lines_work_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_work_center_id_foreign FOREIGN KEY (work_center_id) REFERENCES public.work_centers(id);


--
-- Name: production_journal_lines production_journal_lines_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_lines
    ADD CONSTRAINT production_journal_lines_zone_id_foreign FOREIGN KEY (zone_id) REFERENCES public.zones(id);


--
-- Name: production_journal_templates production_journal_templates_default_wip_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_templates
    ADD CONSTRAINT production_journal_templates_default_wip_account_id_foreign FOREIGN KEY (default_wip_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: production_journal_templates production_journal_templates_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_templates
    ADD CONSTRAINT production_journal_templates_number_series_id_foreign FOREIGN KEY (number_series_id) REFERENCES public.number_series(id);


--
-- Name: production_journal_templates production_journal_templates_posting_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_journal_templates
    ADD CONSTRAINT production_journal_templates_posting_number_series_id_foreign FOREIGN KEY (posting_number_series_id) REFERENCES public.number_series(id);


--
-- Name: production_order_components production_order_components_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_components
    ADD CONSTRAINT production_order_components_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: production_order_components production_order_components_production_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_components
    ADD CONSTRAINT production_order_components_production_order_id_foreign FOREIGN KEY (production_order_id) REFERENCES public.production_orders(id) ON DELETE CASCADE;


--
-- Name: production_order_lines production_order_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_lines
    ADD CONSTRAINT production_order_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: production_order_lines production_order_lines_production_bom_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_lines
    ADD CONSTRAINT production_order_lines_production_bom_id_foreign FOREIGN KEY (production_bom_id) REFERENCES public.production_boms(id);


--
-- Name: production_order_lines production_order_lines_production_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_lines
    ADD CONSTRAINT production_order_lines_production_order_id_foreign FOREIGN KEY (production_order_id) REFERENCES public.production_orders(id) ON DELETE CASCADE;


--
-- Name: production_order_lines production_order_lines_routing_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_lines
    ADD CONSTRAINT production_order_lines_routing_id_foreign FOREIGN KEY (routing_id) REFERENCES public.routings(id);


--
-- Name: production_order_routing_lines production_order_routing_lines_machine_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_routing_lines
    ADD CONSTRAINT production_order_routing_lines_machine_center_id_foreign FOREIGN KEY (machine_center_id) REFERENCES public.machine_centers(id);


--
-- Name: production_order_routing_lines production_order_routing_lines_production_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_routing_lines
    ADD CONSTRAINT production_order_routing_lines_production_order_id_foreign FOREIGN KEY (production_order_id) REFERENCES public.production_orders(id) ON DELETE CASCADE;


--
-- Name: production_order_routing_lines production_order_routing_lines_work_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_order_routing_lines
    ADD CONSTRAINT production_order_routing_lines_work_center_id_foreign FOREIGN KEY (work_center_id) REFERENCES public.work_centers(id);


--
-- Name: production_orders production_orders_capex_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_capex_project_id_foreign FOREIGN KEY (capex_project_id) REFERENCES public.capex_projects(id) ON DELETE SET NULL;


--
-- Name: production_orders production_orders_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: production_orders production_orders_finished_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_finished_by_foreign FOREIGN KEY (finished_by) REFERENCES public.users(id);


--
-- Name: production_orders production_orders_general_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_general_business_posting_group_id_foreign FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: production_orders production_orders_general_product_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_general_product_posting_group_id_foreign FOREIGN KEY (general_product_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: production_orders production_orders_inventory_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_inventory_posting_group_id_foreign FOREIGN KEY (inventory_posting_group_id) REFERENCES public.inventory_posting_groups(id);


--
-- Name: production_orders production_orders_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: production_orders production_orders_last_modified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_last_modified_by_foreign FOREIGN KEY (last_modified_by) REFERENCES public.users(id);


--
-- Name: production_orders production_orders_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id);


--
-- Name: production_orders production_orders_production_bom_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_production_bom_id_foreign FOREIGN KEY (production_bom_id) REFERENCES public.production_boms(id);


--
-- Name: production_orders production_orders_routing_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_orders
    ADD CONSTRAINT production_orders_routing_id_foreign FOREIGN KEY (routing_id) REFERENCES public.routings(id);


--
-- Name: purchase_credit_memo_lines purchase_credit_memo_lines_general_product_posting_group_id_for; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memo_lines
    ADD CONSTRAINT purchase_credit_memo_lines_general_product_posting_group_id_for FOREIGN KEY (general_product_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: purchase_credit_memo_lines purchase_credit_memo_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memo_lines
    ADD CONSTRAINT purchase_credit_memo_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: purchase_credit_memo_lines purchase_credit_memo_lines_purchase_credit_memo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memo_lines
    ADD CONSTRAINT purchase_credit_memo_lines_purchase_credit_memo_id_foreign FOREIGN KEY (purchase_credit_memo_id) REFERENCES public.purchase_credit_memos(id) ON DELETE CASCADE;


--
-- Name: purchase_credit_memos purchase_credit_memos_approver_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memos
    ADD CONSTRAINT purchase_credit_memos_approver_id_foreign FOREIGN KEY (approver_id) REFERENCES public.users(id);


--
-- Name: purchase_credit_memos purchase_credit_memos_corrects_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memos
    ADD CONSTRAINT purchase_credit_memos_corrects_invoice_id_foreign FOREIGN KEY (corrects_invoice_id) REFERENCES public.purchase_invoices(id);


--
-- Name: purchase_credit_memos purchase_credit_memos_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memos
    ADD CONSTRAINT purchase_credit_memos_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: purchase_credit_memos purchase_credit_memos_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memos
    ADD CONSTRAINT purchase_credit_memos_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: purchase_credit_memos purchase_credit_memos_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_credit_memos
    ADD CONSTRAINT purchase_credit_memos_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: purchase_invoice_lines purchase_invoice_lines_general_product_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoice_lines
    ADD CONSTRAINT purchase_invoice_lines_general_product_posting_group_id_foreign FOREIGN KEY (general_product_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: purchase_invoice_lines purchase_invoice_lines_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoice_lines
    ADD CONSTRAINT purchase_invoice_lines_gl_account_id_foreign FOREIGN KEY (gl_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: purchase_invoice_lines purchase_invoice_lines_inventory_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoice_lines
    ADD CONSTRAINT purchase_invoice_lines_inventory_posting_group_id_foreign FOREIGN KEY (inventory_posting_group_id) REFERENCES public.inventory_posting_groups(id);


--
-- Name: purchase_invoice_lines purchase_invoice_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoice_lines
    ADD CONSTRAINT purchase_invoice_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: purchase_invoice_lines purchase_invoice_lines_purchase_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoice_lines
    ADD CONSTRAINT purchase_invoice_lines_purchase_invoice_id_foreign FOREIGN KEY (purchase_invoice_id) REFERENCES public.purchase_invoices(id) ON DELETE CASCADE;


--
-- Name: purchase_invoices purchase_invoices_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoices
    ADD CONSTRAINT purchase_invoices_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: purchase_invoices purchase_invoices_cancelled_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoices
    ADD CONSTRAINT purchase_invoices_cancelled_by_foreign FOREIGN KEY (cancelled_by) REFERENCES public.users(id);


--
-- Name: purchase_invoices purchase_invoices_general_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoices
    ADD CONSTRAINT purchase_invoices_general_business_posting_group_id_foreign FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: purchase_invoices purchase_invoices_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoices
    ADD CONSTRAINT purchase_invoices_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: purchase_invoices purchase_invoices_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoices
    ADD CONSTRAINT purchase_invoices_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id);


--
-- Name: purchase_invoices purchase_invoices_rejected_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoices
    ADD CONSTRAINT purchase_invoices_rejected_by_foreign FOREIGN KEY (rejected_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: purchase_invoices purchase_invoices_vat_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoices
    ADD CONSTRAINT purchase_invoices_vat_business_posting_group_id_foreign FOREIGN KEY (vat_business_posting_group_id) REFERENCES public.vat_business_posting_groups(id);


--
-- Name: purchase_invoices purchase_invoices_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoices
    ADD CONSTRAINT purchase_invoices_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: purchase_invoices purchase_invoices_vendor_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_invoices
    ADD CONSTRAINT purchase_invoices_vendor_posting_group_id_foreign FOREIGN KEY (vendor_posting_group_id) REFERENCES public.vendor_posting_groups(id);


--
-- Name: purchase_order_lines purchase_order_lines_general_product_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_lines
    ADD CONSTRAINT purchase_order_lines_general_product_posting_group_id_foreign FOREIGN KEY (general_product_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: purchase_order_lines purchase_order_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_lines
    ADD CONSTRAINT purchase_order_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: purchase_order_lines purchase_order_lines_purchase_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_lines
    ADD CONSTRAINT purchase_order_lines_purchase_order_id_foreign FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id) ON DELETE CASCADE;


--
-- Name: purchase_orders purchase_orders_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id);


--
-- Name: purchase_orders purchase_orders_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: purchase_orders purchase_orders_general_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_general_business_posting_group_id_foreign FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: purchase_orders purchase_orders_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: purchase_orders purchase_orders_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: purchase_orders purchase_orders_vendor_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_vendor_posting_group_id_foreign FOREIGN KEY (vendor_posting_group_id) REFERENCES public.vendor_posting_groups(id);


--
-- Name: purchase_prices purchase_prices_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_prices
    ADD CONSTRAINT purchase_prices_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: purchase_prices purchase_prices_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_prices
    ADD CONSTRAINT purchase_prices_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: purchase_quote_approval_entries purchase_quote_approval_entries_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_approval_entries
    ADD CONSTRAINT purchase_quote_approval_entries_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: purchase_quote_approval_entries purchase_quote_approval_entries_approver_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_approval_entries
    ADD CONSTRAINT purchase_quote_approval_entries_approver_id_foreign FOREIGN KEY (approver_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: purchase_quote_approval_entries purchase_quote_approval_entries_delegated_to_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_approval_entries
    ADD CONSTRAINT purchase_quote_approval_entries_delegated_to_foreign FOREIGN KEY (delegated_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: purchase_quote_approval_entries purchase_quote_approval_entries_purchase_quote_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_approval_entries
    ADD CONSTRAINT purchase_quote_approval_entries_purchase_quote_id_foreign FOREIGN KEY (purchase_quote_id) REFERENCES public.purchase_quotes(id) ON DELETE CASCADE;


--
-- Name: purchase_quote_approval_entries purchase_quote_approval_entries_rejected_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_approval_entries
    ADD CONSTRAINT purchase_quote_approval_entries_rejected_by_foreign FOREIGN KEY (rejected_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: purchase_quote_archives purchase_quote_archives_archived_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_archives
    ADD CONSTRAINT purchase_quote_archives_archived_by_foreign FOREIGN KEY (archived_by) REFERENCES public.users(id);


--
-- Name: purchase_quote_archives purchase_quote_archives_buyer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_archives
    ADD CONSTRAINT purchase_quote_archives_buyer_id_foreign FOREIGN KEY (buyer_id) REFERENCES public.users(id);


--
-- Name: purchase_quote_archives purchase_quote_archives_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_archives
    ADD CONSTRAINT purchase_quote_archives_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id);


--
-- Name: purchase_quote_archives purchase_quote_archives_purchase_quote_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_archives
    ADD CONSTRAINT purchase_quote_archives_purchase_quote_id_foreign FOREIGN KEY (purchase_quote_id) REFERENCES public.purchase_quotes(id) ON DELETE CASCADE;


--
-- Name: purchase_quote_archives purchase_quote_archives_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_archives
    ADD CONSTRAINT purchase_quote_archives_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: purchase_quote_line_archives purchase_quote_line_archives_purchase_quote_archive_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_line_archives
    ADD CONSTRAINT purchase_quote_line_archives_purchase_quote_archive_id_foreign FOREIGN KEY (purchase_quote_archive_id) REFERENCES public.purchase_quote_archives(id) ON DELETE CASCADE;


--
-- Name: purchase_quote_lines purchase_quote_lines_purchase_order_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_lines
    ADD CONSTRAINT purchase_quote_lines_purchase_order_line_id_foreign FOREIGN KEY (purchase_order_line_id) REFERENCES public.purchase_order_lines(id);


--
-- Name: purchase_quote_lines purchase_quote_lines_purchase_quote_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quote_lines
    ADD CONSTRAINT purchase_quote_lines_purchase_quote_id_foreign FOREIGN KEY (purchase_quote_id) REFERENCES public.purchase_quotes(id) ON DELETE CASCADE;


--
-- Name: purchase_quotes purchase_quotes_buyer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quotes
    ADD CONSTRAINT purchase_quotes_buyer_id_foreign FOREIGN KEY (buyer_id) REFERENCES public.users(id);


--
-- Name: purchase_quotes purchase_quotes_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quotes
    ADD CONSTRAINT purchase_quotes_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id);


--
-- Name: purchase_quotes purchase_quotes_released_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quotes
    ADD CONSTRAINT purchase_quotes_released_by_foreign FOREIGN KEY (released_by) REFERENCES public.users(id);


--
-- Name: purchase_quotes purchase_quotes_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_quotes
    ADD CONSTRAINT purchase_quotes_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: purchase_receipt_lines purchase_receipt_lines_purchase_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipt_lines
    ADD CONSTRAINT purchase_receipt_lines_purchase_order_id_foreign FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id) ON DELETE SET NULL;


--
-- Name: purchase_receipt_lines purchase_receipt_lines_purchase_receipt_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipt_lines
    ADD CONSTRAINT purchase_receipt_lines_purchase_receipt_id_foreign FOREIGN KEY (purchase_receipt_id) REFERENCES public.purchase_receipts(id) ON DELETE CASCADE;


--
-- Name: purchase_receipts purchase_receipts_buyer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipts
    ADD CONSTRAINT purchase_receipts_buyer_id_foreign FOREIGN KEY (buyer_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: purchase_receipts purchase_receipts_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipts
    ADD CONSTRAINT purchase_receipts_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: purchase_receipts purchase_receipts_last_modified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipts
    ADD CONSTRAINT purchase_receipts_last_modified_by_foreign FOREIGN KEY (last_modified_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: purchase_receipts purchase_receipts_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipts
    ADD CONSTRAINT purchase_receipts_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: purchase_receipts purchase_receipts_purchase_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipts
    ADD CONSTRAINT purchase_receipts_purchase_order_id_foreign FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id) ON DELETE SET NULL;


--
-- Name: purchase_receipts purchase_receipts_receiving_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipts
    ADD CONSTRAINT purchase_receipts_receiving_location_id_foreign FOREIGN KEY (receiving_location_id) REFERENCES public.locations(id) ON DELETE SET NULL;


--
-- Name: purchase_receipts purchase_receipts_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_receipts
    ADD CONSTRAINT purchase_receipts_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id) ON DELETE CASCADE;


--
-- Name: putaway_worksheet_lines putaway_worksheet_lines_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.putaway_worksheet_lines
    ADD CONSTRAINT putaway_worksheet_lines_bin_id_foreign FOREIGN KEY (bin_id) REFERENCES public.bins(id);


--
-- Name: putaway_worksheet_lines putaway_worksheet_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.putaway_worksheet_lines
    ADD CONSTRAINT putaway_worksheet_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: putaway_worksheet_lines putaway_worksheet_lines_putaway_worksheet_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.putaway_worksheet_lines
    ADD CONSTRAINT putaway_worksheet_lines_putaway_worksheet_id_foreign FOREIGN KEY (putaway_worksheet_id) REFERENCES public.putaway_worksheets(id) ON DELETE CASCADE;


--
-- Name: putaway_worksheet_lines putaway_worksheet_lines_warehouse_receipt_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.putaway_worksheet_lines
    ADD CONSTRAINT putaway_worksheet_lines_warehouse_receipt_id_foreign FOREIGN KEY (warehouse_receipt_id) REFERENCES public.warehouse_receipts(id);


--
-- Name: putaway_worksheets putaway_worksheets_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.putaway_worksheets
    ADD CONSTRAINT putaway_worksheets_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: putaway_worksheets putaway_worksheets_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.putaway_worksheets
    ADD CONSTRAINT putaway_worksheets_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: recurring_expenses recurring_expenses_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_expenses
    ADD CONSTRAINT recurring_expenses_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.expense_categories(id);


--
-- Name: recurring_expenses recurring_expenses_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_expenses
    ADD CONSTRAINT recurring_expenses_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id);


--
-- Name: recurring_expenses recurring_expenses_dimension_set_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_expenses
    ADD CONSTRAINT recurring_expenses_dimension_set_id_foreign FOREIGN KEY (dimension_set_id) REFERENCES public.dimension_sets(id);


--
-- Name: recurring_expenses recurring_expenses_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_expenses
    ADD CONSTRAINT recurring_expenses_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: recurring_journal_batches recurring_journal_batches_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_batches
    ADD CONSTRAINT recurring_journal_batches_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: recurring_journal_batches recurring_journal_batches_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_batches
    ADD CONSTRAINT recurring_journal_batches_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.recurring_journal_templates(id) ON DELETE CASCADE;


--
-- Name: recurring_journal_lines recurring_journal_lines_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_lines
    ADD CONSTRAINT recurring_journal_lines_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: recurring_journal_lines recurring_journal_lines_allocation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_lines
    ADD CONSTRAINT recurring_journal_lines_allocation_id_foreign FOREIGN KEY (allocation_id) REFERENCES public.allocations(id);


--
-- Name: recurring_journal_lines recurring_journal_lines_balancing_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_lines
    ADD CONSTRAINT recurring_journal_lines_balancing_account_id_foreign FOREIGN KEY (balancing_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: recurring_journal_lines recurring_journal_lines_batch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_lines
    ADD CONSTRAINT recurring_journal_lines_batch_id_foreign FOREIGN KEY (batch_id) REFERENCES public.recurring_journal_batches(id) ON DELETE CASCADE;


--
-- Name: recurring_journal_lines recurring_journal_lines_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_lines
    ADD CONSTRAINT recurring_journal_lines_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: recurring_journal_templates recurring_journal_templates_default_balancing_account_id_foreig; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_templates
    ADD CONSTRAINT recurring_journal_templates_default_balancing_account_id_foreig FOREIGN KEY (default_balancing_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: recurring_journal_templates recurring_journal_templates_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_templates
    ADD CONSTRAINT recurring_journal_templates_number_series_id_foreign FOREIGN KEY (number_series_id) REFERENCES public.number_series(id);


--
-- Name: recurring_journal_templates recurring_journal_templates_posting_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_journal_templates
    ADD CONSTRAINT recurring_journal_templates_posting_number_series_id_foreign FOREIGN KEY (posting_number_series_id) REFERENCES public.number_series(id);


--
-- Name: resource_journal_lines resource_journal_lines_job_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resource_journal_lines
    ADD CONSTRAINT resource_journal_lines_job_id_foreign FOREIGN KEY (job_id) REFERENCES public.jobs(id);


--
-- Name: resource_journal_lines resource_journal_lines_journal_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resource_journal_lines
    ADD CONSTRAINT resource_journal_lines_journal_line_id_foreign FOREIGN KEY (journal_line_id) REFERENCES public.journal_lines(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: routing_lines routing_lines_machine_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_lines
    ADD CONSTRAINT routing_lines_machine_center_id_foreign FOREIGN KEY (machine_center_id) REFERENCES public.machine_centers(id);


--
-- Name: routing_lines routing_lines_routing_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_lines
    ADD CONSTRAINT routing_lines_routing_id_foreign FOREIGN KEY (routing_id) REFERENCES public.routings(id) ON DELETE CASCADE;


--
-- Name: routing_lines routing_lines_subcontractor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_lines
    ADD CONSTRAINT routing_lines_subcontractor_id_foreign FOREIGN KEY (subcontractor_id) REFERENCES public.vendors(id);


--
-- Name: routing_lines routing_lines_work_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_lines
    ADD CONSTRAINT routing_lines_work_center_id_foreign FOREIGN KEY (work_center_id) REFERENCES public.work_centers(id);


--
-- Name: routing_version_lines routing_version_lines_machine_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_version_lines
    ADD CONSTRAINT routing_version_lines_machine_center_id_foreign FOREIGN KEY (machine_center_id) REFERENCES public.machine_centers(id) ON DELETE SET NULL;


--
-- Name: routing_version_lines routing_version_lines_routing_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_version_lines
    ADD CONSTRAINT routing_version_lines_routing_version_id_foreign FOREIGN KEY (routing_version_id) REFERENCES public.routing_versions(id) ON DELETE CASCADE;


--
-- Name: routing_version_lines routing_version_lines_subcontractor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_version_lines
    ADD CONSTRAINT routing_version_lines_subcontractor_id_foreign FOREIGN KEY (subcontractor_id) REFERENCES public.vendors(id) ON DELETE SET NULL;


--
-- Name: routing_version_lines routing_version_lines_work_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_version_lines
    ADD CONSTRAINT routing_version_lines_work_center_id_foreign FOREIGN KEY (work_center_id) REFERENCES public.work_centers(id) ON DELETE SET NULL;


--
-- Name: routing_versions routing_versions_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_versions
    ADD CONSTRAINT routing_versions_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: routing_versions routing_versions_last_modified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_versions
    ADD CONSTRAINT routing_versions_last_modified_by_foreign FOREIGN KEY (last_modified_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: routing_versions routing_versions_routing_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routing_versions
    ADD CONSTRAINT routing_versions_routing_id_foreign FOREIGN KEY (routing_id) REFERENCES public.routings(id) ON DELETE CASCADE;


--
-- Name: routings routings_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routings
    ADD CONSTRAINT routings_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: routings routings_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routings
    ADD CONSTRAINT routings_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: routings routings_last_modified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.routings
    ADD CONSTRAINT routings_last_modified_by_foreign FOREIGN KEY (last_modified_by) REFERENCES public.users(id);


--
-- Name: sales_credit_memo_lines sales_credit_memo_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_credit_memo_lines
    ADD CONSTRAINT sales_credit_memo_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id) ON DELETE CASCADE;


--
-- Name: sales_credit_memo_lines sales_credit_memo_lines_sales_credit_memo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_credit_memo_lines
    ADD CONSTRAINT sales_credit_memo_lines_sales_credit_memo_id_foreign FOREIGN KEY (sales_credit_memo_id) REFERENCES public.sales_credit_memos(id) ON DELETE CASCADE;


--
-- Name: sales_credit_memo_lines sales_credit_memo_lines_sales_invoice_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_credit_memo_lines
    ADD CONSTRAINT sales_credit_memo_lines_sales_invoice_line_id_foreign FOREIGN KEY (sales_invoice_line_id) REFERENCES public.sales_invoice_lines(id) ON DELETE SET NULL;


--
-- Name: sales_credit_memos sales_credit_memos_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_credit_memos
    ADD CONSTRAINT sales_credit_memos_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;


--
-- Name: sales_credit_memos sales_credit_memos_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_credit_memos
    ADD CONSTRAINT sales_credit_memos_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: sales_credit_memos sales_credit_memos_sales_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_credit_memos
    ADD CONSTRAINT sales_credit_memos_sales_invoice_id_foreign FOREIGN KEY (sales_invoice_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: sales_invoice_lines sales_invoice_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_invoice_lines
    ADD CONSTRAINT sales_invoice_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: sales_invoice_lines sales_invoice_lines_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_invoice_lines
    ADD CONSTRAINT sales_invoice_lines_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: sales_invoice_lines sales_invoice_lines_sales_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_invoice_lines
    ADD CONSTRAINT sales_invoice_lines_sales_invoice_id_foreign FOREIGN KEY (sales_invoice_id) REFERENCES public.sales_invoices(id) ON DELETE CASCADE;


--
-- Name: sales_invoices sales_invoices_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_invoices
    ADD CONSTRAINT sales_invoices_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: sales_invoices sales_invoices_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_invoices
    ADD CONSTRAINT sales_invoices_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: sales_invoices sales_invoices_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_invoices
    ADD CONSTRAINT sales_invoices_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id);


--
-- Name: sales_invoices sales_invoices_sales_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_invoices
    ADD CONSTRAINT sales_invoices_sales_order_id_foreign FOREIGN KEY (sales_order_id) REFERENCES public.sales_orders(id) ON DELETE SET NULL;


--
-- Name: sales_order_lines sales_order_lines_general_product_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_order_lines
    ADD CONSTRAINT sales_order_lines_general_product_posting_group_id_foreign FOREIGN KEY (general_product_posting_group_id) REFERENCES public.general_product_posting_groups(id);


--
-- Name: sales_order_lines sales_order_lines_inventory_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_order_lines
    ADD CONSTRAINT sales_order_lines_inventory_posting_group_id_foreign FOREIGN KEY (inventory_posting_group_id) REFERENCES public.inventory_posting_groups(id);


--
-- Name: sales_order_lines sales_order_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_order_lines
    ADD CONSTRAINT sales_order_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: sales_order_lines sales_order_lines_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_order_lines
    ADD CONSTRAINT sales_order_lines_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: sales_order_lines sales_order_lines_pricing_master_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_order_lines
    ADD CONSTRAINT sales_order_lines_pricing_master_id_foreign FOREIGN KEY (pricing_master_id) REFERENCES public.pricing_master(id);


--
-- Name: sales_order_lines sales_order_lines_sales_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_order_lines
    ADD CONSTRAINT sales_order_lines_sales_order_id_foreign FOREIGN KEY (sales_order_id) REFERENCES public.sales_orders(id) ON DELETE CASCADE;


--
-- Name: sales_orders sales_orders_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id);


--
-- Name: sales_orders sales_orders_assigned_warehouse_worker_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_assigned_warehouse_worker_id_foreign FOREIGN KEY (assigned_warehouse_worker_id) REFERENCES public.users(id);


--
-- Name: sales_orders sales_orders_cancelled_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_cancelled_by_foreign FOREIGN KEY (cancelled_by) REFERENCES public.users(id);


--
-- Name: sales_orders sales_orders_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: sales_orders sales_orders_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: sales_orders sales_orders_customer_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_customer_posting_group_id_foreign FOREIGN KEY (customer_posting_group_id) REFERENCES public.customer_posting_groups(id);


--
-- Name: sales_orders sales_orders_general_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_general_business_posting_group_id_foreign FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: sales_orders sales_orders_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: sales_orders sales_orders_pricing_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_pricing_group_id_foreign FOREIGN KEY (pricing_group_id) REFERENCES public.pricing_groups(id);


--
-- Name: sales_orders sales_orders_salesperson_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_salesperson_id_foreign FOREIGN KEY (salesperson_id) REFERENCES public.users(id);


--
-- Name: sales_orders sales_orders_vat_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_orders
    ADD CONSTRAINT sales_orders_vat_business_posting_group_id_foreign FOREIGN KEY (vat_business_posting_group_id) REFERENCES public.vat_business_posting_groups(id);


--
-- Name: sales_quote_items sales_quote_items_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_quote_items
    ADD CONSTRAINT sales_quote_items_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: sales_quote_items sales_quote_items_sales_quote_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_quote_items
    ADD CONSTRAINT sales_quote_items_sales_quote_id_foreign FOREIGN KEY (sales_quote_id) REFERENCES public.sales_quotes(id) ON DELETE CASCADE;


--
-- Name: sales_quote_revisions sales_quote_revisions_sales_quote_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_quote_revisions
    ADD CONSTRAINT sales_quote_revisions_sales_quote_id_foreign FOREIGN KEY (sales_quote_id) REFERENCES public.sales_quotes(id) ON DELETE CASCADE;


--
-- Name: sales_quotes sales_quotes_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_quotes
    ADD CONSTRAINT sales_quotes_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id);


--
-- Name: sales_quotes sales_quotes_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_quotes
    ADD CONSTRAINT sales_quotes_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: sales_shipment_headers sales_shipment_headers_dimension_set_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_shipment_headers
    ADD CONSTRAINT sales_shipment_headers_dimension_set_id_foreign FOREIGN KEY (dimension_set_id) REFERENCES public.dimension_sets(id);


--
-- Name: sales_shipment_headers sales_shipment_headers_sales_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_shipment_headers
    ADD CONSTRAINT sales_shipment_headers_sales_order_id_foreign FOREIGN KEY (sales_order_id) REFERENCES public.sales_orders(id);


--
-- Name: sales_shipment_lines sales_shipment_lines_sales_order_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_shipment_lines
    ADD CONSTRAINT sales_shipment_lines_sales_order_line_id_foreign FOREIGN KEY (sales_order_line_id) REFERENCES public.sales_order_lines(id);


--
-- Name: sales_shipment_lines sales_shipment_lines_sales_shipment_header_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_shipment_lines
    ADD CONSTRAINT sales_shipment_lines_sales_shipment_header_id_foreign FOREIGN KEY (sales_shipment_header_id) REFERENCES public.sales_shipment_headers(id);


--
-- Name: salesperson_purchasers salesperson_purchasers_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salesperson_purchasers
    ADD CONSTRAINT salesperson_purchasers_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: shipment_methods shipment_methods_default_shipping_agent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shipment_methods
    ADD CONSTRAINT shipment_methods_default_shipping_agent_id_foreign FOREIGN KEY (default_shipping_agent_id) REFERENCES public.shipping_agents(id);


--
-- Name: tax_brackets tax_brackets_tax_table_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_brackets
    ADD CONSTRAINT tax_brackets_tax_table_id_foreign FOREIGN KEY (tax_table_id) REFERENCES public.tax_tables(id) ON DELETE CASCADE;


--
-- Name: users users_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: users users_salesperson_code_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_salesperson_code_foreign FOREIGN KEY (salesperson_code) REFERENCES public.salesperson_purchasers(code) ON DELETE SET NULL;


--
-- Name: vat_masters vat_masters_purchase_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_masters
    ADD CONSTRAINT vat_masters_purchase_account_id_foreign FOREIGN KEY (purchase_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: vat_masters vat_masters_sales_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_masters
    ADD CONSTRAINT vat_masters_sales_account_id_foreign FOREIGN KEY (sales_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: vat_posting_setups vat_posting_setups_purchase_vat_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_posting_setups
    ADD CONSTRAINT vat_posting_setups_purchase_vat_account_id_foreign FOREIGN KEY (purchase_vat_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: vat_posting_setups vat_posting_setups_reverse_charge_vat_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_posting_setups
    ADD CONSTRAINT vat_posting_setups_reverse_charge_vat_account_id_foreign FOREIGN KEY (reverse_charge_vat_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: vat_posting_setups vat_posting_setups_sales_vat_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_posting_setups
    ADD CONSTRAINT vat_posting_setups_sales_vat_account_id_foreign FOREIGN KEY (sales_vat_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: vat_posting_setups vat_posting_setups_vat_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_posting_setups
    ADD CONSTRAINT vat_posting_setups_vat_business_posting_group_id_foreign FOREIGN KEY (vat_business_posting_group_id) REFERENCES public.vat_business_posting_groups(id) ON DELETE CASCADE;


--
-- Name: vat_posting_setups vat_posting_setups_vat_product_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vat_posting_setups
    ADD CONSTRAINT vat_posting_setups_vat_product_posting_group_id_foreign FOREIGN KEY (vat_product_posting_group_id) REFERENCES public.vat_product_posting_groups(id) ON DELETE CASCADE;


--
-- Name: vendor_invoice_lines vendor_invoice_lines_capex_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoice_lines
    ADD CONSTRAINT vendor_invoice_lines_capex_project_id_foreign FOREIGN KEY (capex_project_id) REFERENCES public.capex_projects(id);


--
-- Name: vendor_invoice_lines vendor_invoice_lines_capex_project_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoice_lines
    ADD CONSTRAINT vendor_invoice_lines_capex_project_line_id_foreign FOREIGN KEY (capex_project_line_id) REFERENCES public.capex_project_lines(id);


--
-- Name: vendor_invoice_lines vendor_invoice_lines_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoice_lines
    ADD CONSTRAINT vendor_invoice_lines_gl_account_id_foreign FOREIGN KEY (gl_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: vendor_invoice_lines vendor_invoice_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoice_lines
    ADD CONSTRAINT vendor_invoice_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: vendor_invoice_lines vendor_invoice_lines_production_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoice_lines
    ADD CONSTRAINT vendor_invoice_lines_production_order_id_foreign FOREIGN KEY (production_order_id) REFERENCES public.production_orders(id);


--
-- Name: vendor_invoice_lines vendor_invoice_lines_purchase_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoice_lines
    ADD CONSTRAINT vendor_invoice_lines_purchase_order_id_foreign FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id);


--
-- Name: vendor_invoice_lines vendor_invoice_lines_purchase_receipt_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoice_lines
    ADD CONSTRAINT vendor_invoice_lines_purchase_receipt_id_foreign FOREIGN KEY (purchase_receipt_id) REFERENCES public.purchase_receipts(id);


--
-- Name: vendor_invoice_lines vendor_invoice_lines_vendor_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoice_lines
    ADD CONSTRAINT vendor_invoice_lines_vendor_invoice_id_foreign FOREIGN KEY (vendor_invoice_id) REFERENCES public.vendor_invoices(id) ON DELETE CASCADE;


--
-- Name: vendor_invoices vendor_invoices_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoices
    ADD CONSTRAINT vendor_invoices_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id);


--
-- Name: vendor_invoices vendor_invoices_capex_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoices
    ADD CONSTRAINT vendor_invoices_capex_project_id_foreign FOREIGN KEY (capex_project_id) REFERENCES public.capex_projects(id);


--
-- Name: vendor_invoices vendor_invoices_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoices
    ADD CONSTRAINT vendor_invoices_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: vendor_invoices vendor_invoices_expense_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoices
    ADD CONSTRAINT vendor_invoices_expense_gl_account_id_foreign FOREIGN KEY (expense_gl_account_id) REFERENCES public.gl_accounts(id);


--
-- Name: vendor_invoices vendor_invoices_last_modified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoices
    ADD CONSTRAINT vendor_invoices_last_modified_by_foreign FOREIGN KEY (last_modified_by) REFERENCES public.users(id);


--
-- Name: vendor_invoices vendor_invoices_payable_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoices
    ADD CONSTRAINT vendor_invoices_payable_gl_account_id_foreign FOREIGN KEY (payable_gl_account_id) REFERENCES public.gl_accounts(id);


--
-- Name: vendor_invoices vendor_invoices_posted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoices
    ADD CONSTRAINT vendor_invoices_posted_by_foreign FOREIGN KEY (posted_by) REFERENCES public.users(id);


--
-- Name: vendor_invoices vendor_invoices_requested_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoices
    ADD CONSTRAINT vendor_invoices_requested_by_foreign FOREIGN KEY (requested_by) REFERENCES public.users(id);


--
-- Name: vendor_invoices vendor_invoices_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_invoices
    ADD CONSTRAINT vendor_invoices_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: vendor_items vendor_items_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_items
    ADD CONSTRAINT vendor_items_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id) ON DELETE SET NULL;


--
-- Name: vendor_items vendor_items_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_items
    ADD CONSTRAINT vendor_items_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id) ON DELETE CASCADE;


--
-- Name: vendor_items vendor_items_purchase_uom_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_items
    ADD CONSTRAINT vendor_items_purchase_uom_id_foreign FOREIGN KEY (purchase_uom_id) REFERENCES public.unit_of_measures(id) ON DELETE SET NULL;


--
-- Name: vendor_items vendor_items_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_items
    ADD CONSTRAINT vendor_items_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id) ON DELETE CASCADE;


--
-- Name: vendor_ledger_entries vendor_ledger_entries_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_ledger_entries
    ADD CONSTRAINT vendor_ledger_entries_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: vendor_ledger_entries vendor_ledger_entries_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_ledger_entries
    ADD CONSTRAINT vendor_ledger_entries_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id) ON DELETE SET NULL;


--
-- Name: vendor_ledger_entries vendor_ledger_entries_general_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_ledger_entries
    ADD CONSTRAINT vendor_ledger_entries_general_business_posting_group_id_foreign FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: vendor_ledger_entries vendor_ledger_entries_reversed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_ledger_entries
    ADD CONSTRAINT vendor_ledger_entries_reversed_by_foreign FOREIGN KEY (reversed_by) REFERENCES public.users(id);


--
-- Name: vendor_ledger_entries vendor_ledger_entries_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_ledger_entries
    ADD CONSTRAINT vendor_ledger_entries_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: vendor_ledger_entries vendor_ledger_entries_vendor_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_ledger_entries
    ADD CONSTRAINT vendor_ledger_entries_vendor_posting_group_id_foreign FOREIGN KEY (vendor_posting_group_id) REFERENCES public.vendor_posting_groups(id);


--
-- Name: vendor_posting_groups vendor_posting_groups_invoice_rounding_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_posting_groups
    ADD CONSTRAINT vendor_posting_groups_invoice_rounding_account_id_foreign FOREIGN KEY (invoice_rounding_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: vendor_posting_groups vendor_posting_groups_payables_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_posting_groups
    ADD CONSTRAINT vendor_posting_groups_payables_account_id_foreign FOREIGN KEY (payables_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: vendor_posting_groups vendor_posting_groups_payment_disc_credit_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_posting_groups
    ADD CONSTRAINT vendor_posting_groups_payment_disc_credit_account_id_foreign FOREIGN KEY (payment_disc_credit_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: vendor_posting_groups vendor_posting_groups_payment_disc_debit_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_posting_groups
    ADD CONSTRAINT vendor_posting_groups_payment_disc_debit_account_id_foreign FOREIGN KEY (payment_disc_debit_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: vendors vendors_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE CASCADE;


--
-- Name: vendors vendors_general_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_general_business_posting_group_id_foreign FOREIGN KEY (general_business_posting_group_id) REFERENCES public.general_business_posting_groups(id);


--
-- Name: vendors vendors_vat_business_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_vat_business_posting_group_id_foreign FOREIGN KEY (vat_business_posting_group_id) REFERENCES public.vat_business_posting_groups(id);


--
-- Name: vendors vendors_vendor_posting_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_vendor_posting_group_id_foreign FOREIGN KEY (vendor_posting_group_id) REFERENCES public.vendor_posting_groups(id);


--
-- Name: warehouse_activities warehouse_activities_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activities
    ADD CONSTRAINT warehouse_activities_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: warehouse_activities warehouse_activities_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activities
    ADD CONSTRAINT warehouse_activities_bin_id_foreign FOREIGN KEY (bin_id) REFERENCES public.bins(id) ON DELETE SET NULL;


--
-- Name: warehouse_activities warehouse_activities_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activities
    ADD CONSTRAINT warehouse_activities_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: warehouse_activities warehouse_activities_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activities
    ADD CONSTRAINT warehouse_activities_zone_id_foreign FOREIGN KEY (zone_id) REFERENCES public.zones(id) ON DELETE SET NULL;


--
-- Name: warehouse_activity_lines warehouse_activity_lines_destination_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activity_lines
    ADD CONSTRAINT warehouse_activity_lines_destination_bin_id_foreign FOREIGN KEY (destination_bin_id) REFERENCES public.bins(id) ON DELETE SET NULL;


--
-- Name: warehouse_activity_lines warehouse_activity_lines_destination_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activity_lines
    ADD CONSTRAINT warehouse_activity_lines_destination_zone_id_foreign FOREIGN KEY (destination_zone_id) REFERENCES public.zones(id) ON DELETE SET NULL;


--
-- Name: warehouse_activity_lines warehouse_activity_lines_handled_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activity_lines
    ADD CONSTRAINT warehouse_activity_lines_handled_by_foreign FOREIGN KEY (handled_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: warehouse_activity_lines warehouse_activity_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activity_lines
    ADD CONSTRAINT warehouse_activity_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: warehouse_activity_lines warehouse_activity_lines_source_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activity_lines
    ADD CONSTRAINT warehouse_activity_lines_source_bin_id_foreign FOREIGN KEY (source_bin_id) REFERENCES public.bins(id) ON DELETE SET NULL;


--
-- Name: warehouse_activity_lines warehouse_activity_lines_source_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activity_lines
    ADD CONSTRAINT warehouse_activity_lines_source_zone_id_foreign FOREIGN KEY (source_zone_id) REFERENCES public.zones(id) ON DELETE SET NULL;


--
-- Name: warehouse_activity_lines warehouse_activity_lines_warehouse_activity_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_activity_lines
    ADD CONSTRAINT warehouse_activity_lines_warehouse_activity_id_foreign FOREIGN KEY (warehouse_activity_id) REFERENCES public.warehouse_activities(id) ON DELETE CASCADE;


--
-- Name: warehouse_entries warehouse_entries_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_entries
    ADD CONSTRAINT warehouse_entries_bin_id_foreign FOREIGN KEY (bin_id) REFERENCES public.bins(id) ON DELETE SET NULL;


--
-- Name: warehouse_entries warehouse_entries_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_entries
    ADD CONSTRAINT warehouse_entries_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: warehouse_entries warehouse_entries_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_entries
    ADD CONSTRAINT warehouse_entries_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: warehouse_entries warehouse_entries_item_ledger_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_entries
    ADD CONSTRAINT warehouse_entries_item_ledger_entry_id_foreign FOREIGN KEY (item_ledger_entry_id) REFERENCES public.item_ledger_entries(id) ON DELETE SET NULL;


--
-- Name: warehouse_entries warehouse_entries_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_entries
    ADD CONSTRAINT warehouse_entries_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: warehouse_entries warehouse_entries_warehouse_activity_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_entries
    ADD CONSTRAINT warehouse_entries_warehouse_activity_line_id_foreign FOREIGN KEY (warehouse_activity_line_id) REFERENCES public.warehouse_activity_lines(id) ON DELETE SET NULL;


--
-- Name: warehouse_entries warehouse_entries_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_entries
    ADD CONSTRAINT warehouse_entries_zone_id_foreign FOREIGN KEY (zone_id) REFERENCES public.zones(id) ON DELETE SET NULL;


--
-- Name: warehouse_journal_batches warehouse_journal_batches_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_batches
    ADD CONSTRAINT warehouse_journal_batches_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: warehouse_journal_batches warehouse_journal_batches_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_batches
    ADD CONSTRAINT warehouse_journal_batches_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: warehouse_journal_batches warehouse_journal_batches_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_batches
    ADD CONSTRAINT warehouse_journal_batches_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.warehouse_journal_templates(id) ON DELETE CASCADE;


--
-- Name: warehouse_journal_batches warehouse_journal_batches_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_batches
    ADD CONSTRAINT warehouse_journal_batches_zone_id_foreign FOREIGN KEY (zone_id) REFERENCES public.zones(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_batch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_batch_id_foreign FOREIGN KEY (batch_id) REFERENCES public.warehouse_journal_batches(id) ON DELETE CASCADE;


--
-- Name: warehouse_journal_lines warehouse_journal_lines_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_bin_id_foreign FOREIGN KEY (bin_id) REFERENCES public.bins(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_destination_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_destination_bin_id_foreign FOREIGN KEY (destination_bin_id) REFERENCES public.bins(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_destination_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_destination_location_id_foreign FOREIGN KEY (destination_location_id) REFERENCES public.locations(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_destination_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_destination_zone_id_foreign FOREIGN KEY (destination_zone_id) REFERENCES public.zones(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_source_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_source_bin_id_foreign FOREIGN KEY (source_bin_id) REFERENCES public.bins(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_source_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_source_location_id_foreign FOREIGN KEY (source_location_id) REFERENCES public.locations(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_source_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_source_zone_id_foreign FOREIGN KEY (source_zone_id) REFERENCES public.zones(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_warehouse_activity_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_warehouse_activity_id_foreign FOREIGN KEY (warehouse_activity_id) REFERENCES public.warehouse_activities(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_warehouse_activity_line_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_warehouse_activity_line_id_foreign FOREIGN KEY (warehouse_activity_line_id) REFERENCES public.warehouse_activity_lines(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_warehouse_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_warehouse_entry_id_foreign FOREIGN KEY (warehouse_entry_id) REFERENCES public.warehouse_entries(id);


--
-- Name: warehouse_journal_lines warehouse_journal_lines_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_lines
    ADD CONSTRAINT warehouse_journal_lines_zone_id_foreign FOREIGN KEY (zone_id) REFERENCES public.zones(id);


--
-- Name: warehouse_journal_templates warehouse_journal_templates_default_adjustment_account_id_forei; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_templates
    ADD CONSTRAINT warehouse_journal_templates_default_adjustment_account_id_forei FOREIGN KEY (default_adjustment_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: warehouse_journal_templates warehouse_journal_templates_number_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_journal_templates
    ADD CONSTRAINT warehouse_journal_templates_number_series_id_foreign FOREIGN KEY (number_series_id) REFERENCES public.number_series(id);


--
-- Name: warehouse_pick_lines warehouse_pick_lines_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_pick_lines
    ADD CONSTRAINT warehouse_pick_lines_bin_id_foreign FOREIGN KEY (bin_id) REFERENCES public.bins(id) ON DELETE SET NULL;


--
-- Name: warehouse_pick_lines warehouse_pick_lines_destination_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_pick_lines
    ADD CONSTRAINT warehouse_pick_lines_destination_bin_id_foreign FOREIGN KEY (destination_bin_id) REFERENCES public.bins(id) ON DELETE SET NULL;


--
-- Name: warehouse_pick_lines warehouse_pick_lines_destination_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_pick_lines
    ADD CONSTRAINT warehouse_pick_lines_destination_zone_id_foreign FOREIGN KEY (destination_zone_id) REFERENCES public.zones(id) ON DELETE SET NULL;


--
-- Name: warehouse_pick_lines warehouse_pick_lines_handled_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_pick_lines
    ADD CONSTRAINT warehouse_pick_lines_handled_by_foreign FOREIGN KEY (handled_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: warehouse_pick_lines warehouse_pick_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_pick_lines
    ADD CONSTRAINT warehouse_pick_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: warehouse_pick_lines warehouse_pick_lines_warehouse_pick_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_pick_lines
    ADD CONSTRAINT warehouse_pick_lines_warehouse_pick_id_foreign FOREIGN KEY (warehouse_pick_id) REFERENCES public.warehouse_picks(id) ON DELETE CASCADE;


--
-- Name: warehouse_pick_lines warehouse_pick_lines_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_pick_lines
    ADD CONSTRAINT warehouse_pick_lines_zone_id_foreign FOREIGN KEY (zone_id) REFERENCES public.zones(id) ON DELETE SET NULL;


--
-- Name: warehouse_picks warehouse_picks_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_picks
    ADD CONSTRAINT warehouse_picks_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: warehouse_picks warehouse_picks_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_picks
    ADD CONSTRAINT warehouse_picks_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: warehouse_picks warehouse_picks_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_picks
    ADD CONSTRAINT warehouse_picks_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: warehouse_picks warehouse_picks_warehouse_shipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_picks
    ADD CONSTRAINT warehouse_picks_warehouse_shipment_id_foreign FOREIGN KEY (warehouse_shipment_id) REFERENCES public.warehouse_shipments(id) ON DELETE SET NULL;


--
-- Name: warehouse_putaway_lines warehouse_putaway_lines_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_putaway_lines
    ADD CONSTRAINT warehouse_putaway_lines_bin_id_foreign FOREIGN KEY (bin_id) REFERENCES public.bins(id);


--
-- Name: warehouse_putaway_lines warehouse_putaway_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_putaway_lines
    ADD CONSTRAINT warehouse_putaway_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: warehouse_putaway_lines warehouse_putaway_lines_warehouse_putaway_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_putaway_lines
    ADD CONSTRAINT warehouse_putaway_lines_warehouse_putaway_id_foreign FOREIGN KEY (warehouse_putaway_id) REFERENCES public.warehouse_putaways(id) ON DELETE CASCADE;


--
-- Name: warehouse_putaways warehouse_putaways_assigned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_putaways
    ADD CONSTRAINT warehouse_putaways_assigned_user_id_foreign FOREIGN KEY (assigned_user_id) REFERENCES public.users(id);


--
-- Name: warehouse_putaways warehouse_putaways_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_putaways
    ADD CONSTRAINT warehouse_putaways_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: warehouse_putaways warehouse_putaways_warehouse_receipt_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_putaways
    ADD CONSTRAINT warehouse_putaways_warehouse_receipt_id_foreign FOREIGN KEY (warehouse_receipt_id) REFERENCES public.warehouse_receipts(id);


--
-- Name: warehouse_receipt_lines warehouse_receipt_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_receipt_lines
    ADD CONSTRAINT warehouse_receipt_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: warehouse_receipt_lines warehouse_receipt_lines_warehouse_receipt_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_receipt_lines
    ADD CONSTRAINT warehouse_receipt_lines_warehouse_receipt_id_foreign FOREIGN KEY (warehouse_receipt_id) REFERENCES public.warehouse_receipts(id) ON DELETE CASCADE;


--
-- Name: warehouse_receipts warehouse_receipts_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_receipts
    ADD CONSTRAINT warehouse_receipts_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: warehouse_receipts warehouse_receipts_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_receipts
    ADD CONSTRAINT warehouse_receipts_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: warehouse_requests warehouse_requests_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_requests
    ADD CONSTRAINT warehouse_requests_bin_id_foreign FOREIGN KEY (bin_id) REFERENCES public.bins(id) ON DELETE SET NULL;


--
-- Name: warehouse_requests warehouse_requests_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_requests
    ADD CONSTRAINT warehouse_requests_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: warehouse_requests warehouse_requests_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_requests
    ADD CONSTRAINT warehouse_requests_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: warehouse_requests warehouse_requests_warehouse_activity_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_requests
    ADD CONSTRAINT warehouse_requests_warehouse_activity_id_foreign FOREIGN KEY (warehouse_activity_id) REFERENCES public.warehouse_activities(id) ON DELETE SET NULL;


--
-- Name: warehouse_requests warehouse_requests_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_requests
    ADD CONSTRAINT warehouse_requests_zone_id_foreign FOREIGN KEY (zone_id) REFERENCES public.zones(id) ON DELETE SET NULL;


--
-- Name: warehouse_shipment_lines warehouse_shipment_lines_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_shipment_lines
    ADD CONSTRAINT warehouse_shipment_lines_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.items(id);


--
-- Name: warehouse_shipment_lines warehouse_shipment_lines_warehouse_shipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_shipment_lines
    ADD CONSTRAINT warehouse_shipment_lines_warehouse_shipment_id_foreign FOREIGN KEY (warehouse_shipment_id) REFERENCES public.warehouse_shipments(id) ON DELETE CASCADE;


--
-- Name: warehouse_shipments warehouse_shipments_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_shipments
    ADD CONSTRAINT warehouse_shipments_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: warehouse_shipments warehouse_shipments_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouse_shipments
    ADD CONSTRAINT warehouse_shipments_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: work_center_bins work_center_bins_fixed_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_bins
    ADD CONSTRAINT work_center_bins_fixed_bin_id_foreign FOREIGN KEY (fixed_bin_id) REFERENCES public.bins(id) ON DELETE SET NULL;


--
-- Name: work_center_bins work_center_bins_from_production_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_bins
    ADD CONSTRAINT work_center_bins_from_production_bin_id_foreign FOREIGN KEY (from_production_bin_id) REFERENCES public.bins(id) ON DELETE SET NULL;


--
-- Name: work_center_bins work_center_bins_open_shop_floor_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_bins
    ADD CONSTRAINT work_center_bins_open_shop_floor_bin_id_foreign FOREIGN KEY (open_shop_floor_bin_id) REFERENCES public.bins(id) ON DELETE SET NULL;


--
-- Name: work_center_bins work_center_bins_to_production_bin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_bins
    ADD CONSTRAINT work_center_bins_to_production_bin_id_foreign FOREIGN KEY (to_production_bin_id) REFERENCES public.bins(id) ON DELETE SET NULL;


--
-- Name: work_center_bins work_center_bins_work_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_bins
    ADD CONSTRAINT work_center_bins_work_center_id_foreign FOREIGN KEY (work_center_id) REFERENCES public.work_centers(id) ON DELETE CASCADE;


--
-- Name: work_center_calendars work_center_calendars_work_center_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_center_calendars
    ADD CONSTRAINT work_center_calendars_work_center_id_foreign FOREIGN KEY (work_center_id) REFERENCES public.work_centers(id);


--
-- Name: work_centers work_centers_fixed_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_centers
    ADD CONSTRAINT work_centers_fixed_asset_id_foreign FOREIGN KEY (fixed_asset_id) REFERENCES public.fixed_assets(id) ON DELETE SET NULL;


--
-- Name: work_centers work_centers_operator_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_centers
    ADD CONSTRAINT work_centers_operator_employee_id_foreign FOREIGN KEY (operator_employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: work_centers work_centers_subcontractor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_centers
    ADD CONSTRAINT work_centers_subcontractor_id_foreign FOREIGN KEY (subcontractor_id) REFERENCES public.vendors(id);


--
-- Name: work_centers work_centers_work_center_gl_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_centers
    ADD CONSTRAINT work_centers_work_center_gl_account_id_foreign FOREIGN KEY (work_center_gl_account_id) REFERENCES public.chart_of_accounts(id) ON DELETE SET NULL;


--
-- Name: work_centers work_centers_work_center_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.work_centers
    ADD CONSTRAINT work_centers_work_center_group_id_foreign FOREIGN KEY (work_center_group_id) REFERENCES public.work_center_groups(id);


--
-- Name: zones zones_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.zones
    ADD CONSTRAINT zones_location_id_foreign FOREIGN KEY (location_id) REFERENCES public.locations(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict x8gYo59e93fKyzf8ql9df3furCIn4pgKWAzgicuhIWa23eGVlgtD2imYszJmAV5

--
-- PostgreSQL database dump
--

\restrict mNdqFj6JjFpZNndkvFa1VCDQdPDMKkpl2Pvm4SJRFdKpe5vhNpVU53ReY5fRE48

-- Dumped from database version 18.3 (Ubuntu 18.3-1.pgdg24.04+1)
-- Dumped by pg_dump version 18.3 (Ubuntu 18.3-1.pgdg24.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_03_27_104454_create_categories_table	1
5	2026_03_27_104633_create_unit_of_measures_table	1
6	2026_03_27_105154_create_document_headers_table	1
7	2026_03_27_120956_create_permission_tables	1
8	2026_03_27_213709_create_number_series_table	1
9	2026_03_28_222635_create_gl_accounts_table	1
10	2026_03_28_222637_create_vat_product_posting_groups_table	1
11	2026_03_28_222638_create_vat_business_posting_groups_table	1
12	2026_03_28_223240_create_general_business_posting_groups_table	1
13	2026_03_28_223808_create_general_product_posting_groups_table	1
14	2026_03_28_225023_create_inventory_posting_groups_table	1
15	2026_03_28_225038_create_chart_of_accounts_table	1
16	2026_03_28_225039_create_vendor_posting_groups_table	1
17	2026_03_28_225040_create_vat_masters_table	1
18	2026_03_28_225042_create_vat_posting_setups_table	1
19	2026_03_28_225044_create_general_posting_setups_table	1
20	2026_03_28_225045_create_general_posting_setup_lines_table	1
21	2026_03_28_225050create_customer_posting_groups_table	1
22	2026_03_28_225129_create_locations_table	1
23	2026_03_28_225257_create_inventory_posting_setups_table	1
24	2026_03_28_225267_create_contacts_table	1
25	2026_03_28_225350_create_customer_groups_table	1
26	2026_03_28_225350_create_pricing_groups_table	1
27	2026_03_28_225351_create_customers_table	1
28	2026_03_28_225445_create_vendors_table	1
29	2026_03_28_225959_create_currencies_table	1
30	2026_03_28_230000_increase_currencies_iso_country_code_length	1
31	2026_03_28_230037_create_items_table	1
32	2026_03_28_230040_create_item_skus_table	1
33	2026_03_28_230041_create_item_lots_table	1
34	2026_03_28_230042_create_item_category_assignments_table	1
35	2026_03_28_230043_create_item_uom_assignments_table	1
36	2026_03_28_230044_create_vendor_items_table	1
37	2026_03_28_230045_create_purchase_orders_table	1
38	2026_03_28_230046_create_purchase_order_lines_table	1
39	2026_03_28_230321_create_zones_table	1
40	2026_03_28_230501_create_bins_table	1
41	2026_03_28_230633_create_warehouse_receipts_table	1
42	2026_03_28_231118_create_warehouse_receipt_lines_table	1
43	2026_03_28_231210_create_warehouse_shipments_table	1
44	2026_03_28_231445_create_warehouse_shipment_lines_table	1
45	2026_03_29_104342_create_item_ledger_entries_table	1
46	2026_03_29_104743_create_gl_entries_table	1
47	2026_03_29_111827_create_pricing_masters_table	1
48	2026_03_29_113325_create_pricing_master_quantity_breaks_table	1
49	2026_03_29_113644_create_payroll_posting_groups_table	1
50	2026_03_29_114314_create_purchase_invoices_table	1
51	2026_03_29_114315_create_purchase_invoice_lines_table	1
52	2026_03_29_120540_create_sales_orders_table	1
53	2026_03_29_120547_create_sales_order_lines_table	1
54	2026_03_29_121332_create_posted_sales_invoices_table	1
55	2026_03_29_121427_create_posted_sales_invoice_lines_table	1
56	2026_03_29_122011_create_posted_sales_credit_memos_table	1
57	2026_03_29_122026_create_posted_sales_credit_memo_lines_table	1
58	2026_03_29_122623_create_customer_ledger_entries_table	1
59	2026_03_29_123527_create_bank_accounts_table	1
60	2026_03_29_123528_create_payments_table	1
61	2026_03_29_123624_create_payment_applications_table	1
62	2026_03_29_124057_create_vendor_ledger_entries_table	1
63	2026_03_29_125148_create_posted_purchase_credit_memos_table	1
64	2026_03_29_125306_create_posted_purchase_credit_memo_lines_table	1
65	2026_03_29_130120_create_production_boms_table	1
66	2026_03_29_130121_create_production_bom_lines_table	1
67	2026_03_29_130122_create_production_bom_versions_table	1
68	2026_03_29_130435_create_work_center_groups_table	1
69	2026_03_29_130445_create_work_centers_table	1
70	2026_03_29_130446_create_machine_centers_table	1
71	2026_03_29_130446_create_routings_table	1
72	2026_03_29_130510_create_routing_lines_table	1
73	2026_03_29_130511_create_production_orders_table	1
74	2026_03_29_130511_create_routing_versions_table	1
75	2026_03_29_130613_create_production_order_components_table	1
76	2026_03_29_130649_create_production_order_routing_lines_table	1
77	2026_03_29_134440_create_work_center_calendars_table	1
78	2026_03_29_134444_create_fa_posting_groups_table	1
79	2026_03_29_134449_create_fa_locations_table	1
80	2026_03_29_134450_create_fa_classes_table	1
81	2026_03_29_140028_create_fixed_assets_table	1
82	2026_03_29_140038_create_capex_projects_table	1
83	2026_03_29_140040_create_capacity_ledger_entries_table	1
84	2026_03_29_140731_create_capex_project_lines_table	1
85	2026_03_29_141518_create_fixed_asset_depreciation_ledger_table	1
86	2026_03_29_141803_create_purchase_receipts_table	1
87	2026_03_29_141806_create_vendor_invoices_table	1
88	2026_03_29_141932_create_vendor_invoice_lines_table	1
89	2026_03_29_142607_create_blanket_orders_table	1
90	2026_03_29_143205_create_purchase_receipt_lines_table	1
91	2026_03_29_143213_create_blanket_order_lines_table	1
92	2026_04_02_122305_create_value_entries_table	1
93	2026_04_02_133322_create_production_order_lines_table	1
94	2026_04_04_142938_add_capex_to_production_orders	1
95	2026_04_04_143134_add_sourceable_to_gl_entries	1
96	2026_04_04_203710_create_price_change_templates_table	1
97	2026_04_04_203759_create_price_change_template_lines_table	1
98	2026_04_05_092638_create_discount_rules_table	1
99	2026_04_05_092718_create_campaigns_table	1
100	2026_04_05_092756_create_campaign_items_table	1
101	2026_04_05_093852_create_price_lists_table	1
102	2026_04_05_113437_create_sales_quotes_table	1
103	2026_04_05_113503_create_sales_quote_items_table	1
104	2026_04_05_115604_create_sales_quote_revisions_table	1
105	2026_04_05_115851_create_customer_price_overrides_table	1
106	2026_04_06_081639_create_sales_invoices_table	1
107	2026_04_06_081640_create_sales_invoice_lines_table	1
108	2026_04_06_081641_create_sales_credit_memos_table	1
109	2026_04_06_081737_create_sales_credit_memo_lines_table	1
110	2026_04_07_101927_create_number_series_lines_table	1
111	2026_04_07_105914_create_dimensions_table	1
112	2026_04_07_105957_create_dimension_values_table	1
113	2026_04_07_110023_create_dimension_sets_table	1
114	2026_04_07_110024_create_dimension_set_entries_table	1
115	2026_04_07_110131_create_default_dimensions_table	1
116	2026_04_07_110203_create_general_ledger_setups_table	1
117	2026_04_07_110944_create_dimension_set_tree_nodes_table	1
118	2026_04_07_111536_create_dimension_combinations_table	1
119	2026_04_07_111601_create_dimension_value_combinations_table	1
120	2026_04_07_112000_create_sales_shipment_headers_table	1
121	2026_04_07_112001_create_sales_shipment_lines_table	1
122	2026_04_07_113000_create_item_tracking_lines_table	1
123	2026_04_07_113205_create_reservation_entries_table	1
124	2026_04_08_070646_create_purchase_quotes_table	1
125	2026_04_08_070757_create_purchase_quote_lines_table	1
126	2026_04_08_074249_create_purchase_quote_approval_entries_table	1
127	2026_04_08_124421_create_purchase_prices_table	1
128	2026_04_08_124729_create_purchase_quote_archives_table	1
129	2026_04_08_124731_create_purchase_quote_line_archives_table	1
130	2026_04_08_190541_create_approval_templates_table	1
131	2026_04_08_190556_create_approval_template_entries_table	1
132	2026_04_10_045420_create_purchase_credit_memos_table	1
133	2026_04_10_045421_create_purchase_credit_memo_lines_table	1
134	2026_04_10_045422_create_employee_posting_groups_table	1
135	2026_04_10_054352_create_employees_table	1
136	2026_04_10_062613_create_businesses_table	1
137	2026_04_10_062613_create_factories_table	1
138	2026_04_10_064024_create_employee_compensation_table	1
139	2026_04_10_064024_create_pay_codes_table	1
140	2026_04_10_064025_create_payroll_documents_table	1
141	2026_04_10_064025_create_payroll_lines_table	1
142	2026_04_10_101702_create_departments_table	1
143	2026_04_10_101850_create_department_employees_table	1
144	2026_04_10_113139_add_is_price_inclusive_to_sales_and_purchases	1
145	2026_04_10_115432_refactor_blanket_orders_for_sales_and_purchase	1
146	2026_04_10_130045_create_identity_and_access_tables	1
147	2026_04_10_130500_create_currency_exchange_rates_table	1
148	2026_04_10_131046_add_salesperson_code_to_users_table	1
149	2026_04_10_131831_fix_user_employee_relationship_linking	1
150	2026_04_10_131833_create_bank_account_ledger_entries_table	1
151	2026_04_10_131834_create_currency_buffers_table	1
152	2026_04_10_131835_create_bank_account_statement_lines_table	1
153	2026_04_10_131836_create_bank_reconciliations_table	1
154	2026_04_10_132734_create_currency_adjustment_ledgers_table	1
155	2026_04_10_154101_create_shipping_agents_table	1
156	2026_04_10_154102_create_shipping_agent_services_table	1
157	2026_04_10_154103_create_shipment_methods_table	1
158	2026_04_10_161156_create_payment_terms_table	1
159	2026_04_10_180531_add_fa_fields_to_purchase_lines	1
160	2026_04_10_180535_add_fa_fields_to_posted_purchase_invoice_lines	1
161	2026_04_11_103350_create_comprehensive_assets_structure	1
162	2026_04_11_103500_refactor_fixed_asset_references	1
163	2026_04_11_103800_create_asset_ledger_entries_table	1
164	2026_04_11_104300_create_asset_tracking_tables	1
165	2026_04_11_104500_extend_asset_financial_fields	1
166	2026_04_11_104900_extend_gl_entry_multi_currency	1
167	2026_04_11_111107_create_expense_categories_table	1
168	2026_04_11_130423_rename_product_category_to_category_in_expenses	1
169	2026_04_11_132628_create_account_reports_tables	1
170	2026_04_11_135531_add_department_id_to_employees	1
171	2026_04_11_135926_add_shortcut_dimensions_to_gl_entries	1
172	2026_04_11_141423_fix_coa_income_balance_classification	1
173	2026_04_11_151922_create_warehouse_activities_table	1
174	2026_04_11_152021_create_warehouse_activity_lines_table	1
175	2026_04_11_152022_create_warehouse_entries_table	1
176	2026_04_11_152100_create_warehouse_requests_table	1
177	2026_04_11_152130_create_bin_contents_table	1
178	2026_04_11_152205_create_work_center_bins_table	1
179	2026_04_11_152210_create_item_journal_templates_table	1
180	2026_04_11_152212_create_item_journal_lines_table	1
181	2026_04_11_180501_align_financial_models_with_currency	1
182	2026_04_11_200000_create_business_units_table	1
183	2026_04_11_200001_create_allocations_table	1
184	2026_04_11_205848_create_general_journal_templates_table	1
185	2026_04_11_210007_create_production_journal_templates_table	1
186	2026_04_11_210059_create_warehouse_journal_templates_table	1
187	2026_04_11_210151_create_recurring_journal_templates_table	1
188	2026_04_11_214657_create_fixed_asset_journal_tables	1
189	2026_04_13_152550_add_gl_account_id_to_work_centers_table	1
190	2026_04_13_154245_create_warehouse_picks_table	1
191	2026_04_13_154246_create_warehouse_pick_lines_table	1
192	2026_04_13_170607_create_actual_overhead_costs_table	1
193	2026_04_14_143252_create_maintenance_contracts_table	1
194	2026_04_14_144114_create_maintenance_contract_assets_table	1
195	2026_04_14_144346_create_fa_maintenance_logs_table	1
196	2026_04_16_104501_create_inventory_putaways_table	1
197	2026_04_17_113630_create_payroll_periods_table	1
198	2026_04_17_113659_update_pay_codes_table_v2	1
199	2026_04_17_113727_create_employee_pay_codes_table	1
200	2026_04_17_113738_update_payroll_lines_table_v2	1
201	2026_04_17_113801_update_payroll_documents_table_v2	1
202	2026_04_17_114835_create_payroll_statutory_setups_table	1
203	2026_04_17_115220_drop_check_constraints_on_pay_codes	1
204	2026_04_17_122702_create_tax_tables_table	1
205	2026_04_17_122707_create_social_security_tiers_table	1
206	2026_04_17_122712_create_employee_bank_accounts_table	1
207	2026_04_17_122717_create_employee_ytd_balances_table	1
208	2026_04_17_125310_create_tax_brackets_table	1
209	2026_04_17_125507_create_sync_logs_table	1
210	2026_04_18_142845_add_dimension_set_id_to_expense_budgets	1
211	2026_04_18_145201_add_fixed_asset_and_payroll_links_to_manufacturing	1
212	2026_04_18_151559_add_bc_alignment_to_items_and_bins	1
213	2026_04_18_152600_align_price_change_template_lines_table	1
214	2026_04_18_154126_add_bin_id_to_putaway_worksheet_lines	1
215	2026_04_18_154900_create_recurring_expenses_table	1
216	2026_04_19_100000_create_approval_entries_table	1
217	2026_04_20_170531_create_manufacturing_version_lines_tables	1
218	2026_04_21_165739_create_journal_templates_table	1
219	2026_04_21_170836_create_resource_journal_lines_table	1
220	2026_04_21_171216_create_cash_receipt_lines_table	1
221	2026_04_22_035820_create_journal_posting_services_table	1
222	2026_04_22_040201_create_job_journal_lines_table	1
223	2026_04_22_040241_create_recurring_journals_table	1
224	2026_04_22_125055_create_fa_journal_lines_table	1
225	2026_04_24_105619_backfill_currency_id_on_expense_transactions	1
226	2026_05_09_113740_create_reason_codes_table	1
227	2026_05_09_113839_create_inventory_adjustment_journals_table	1
228	2026_05_09_114000_create_inventory_adjustment_lines_table	1
229	2026_05_09_135805_create_item_tracking_codes_table	1
230	2026_05_09_141624_create_warehouse_setups_table	1
231	2026_05_09_141831_create_physical_inventory_journals_table	1
232	2026_05_09_142015_create_physical_inventory_lines_table	1
233	2026_05_23_144707_add_manufacturing_links_to_items_table	2
234	2026_05_25_150227_add_currency_code_to_purchase_orders_table	3
235	2026_05_26_091036_create_overhead_cost_categories_table	4
236	2026_05_26_111732_add_posting_date_window_to_general_ledger_setup_table	5
237	2026_05_26_111736_create_accounting_periods_table	5
238	2026_05_26_112218_add_closing_flags_to_gl_entries_table	6
239	2026_05_26_112231_add_retained_earnings_account_to_general_ledger_setup_table	6
240	2026_05_26_112605_create_fiscal_reopen_logs_table	7
241	2026_05_27_171852_create_company_information_table	8
242	2026_05_28_121340_add_unique_index_to_users_employee_id	9
243	2026_05_28_122018_create_attendance_ledger_entries_table	10
244	2026_05_29_134817_add_sales_order_id_to_sales_invoices_table	11
245	2026_05_29_160000_align_purchase_invoice_lifecycle_bc_style	12
246	2026_05_29_161500_make_purchase_invoice_posting_fields_nullable	13
247	2026_05_29_190500_add_business_id_to_company_information_table	14
248	2026_05_31_103152_add_audit_note_to_employee_compensation_table	15
249	2026_05_31_103855_create_employee_promotion_histories_table	16
250	2026_06_01_153054_add_default_expense_offset_account_to_general_ledger_setup_table	17
251	2026_06_01_154341_add_title_to_expense_allocations_table	18
252	2026_06_02_165657_create_item_charges_table	19
253	2026_06_03_152616_add_scope_columns_to_price_change_template_lines_table	20
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 253, true);


--
-- PostgreSQL database dump complete
--

\unrestrict mNdqFj6JjFpZNndkvFa1VCDQdPDMKkpl2Pvm4SJRFdKpe5vhNpVU53ReY5fRE48

