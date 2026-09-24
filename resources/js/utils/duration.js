export const durationUtils = {
    get stayDurationLimits() {
        return window.__stayDurationLimits || { minDays: 1, maxDays: 85 };
    },

    handleStayDurationChange() {
        if (this.passengerData.stay_duration === 'Customize (Set Duration)') {
            this.openCustomDurationModal();
        }
    },

    openCustomDurationModal() {
        this.customDurationModalVisible = true;
        this.passengerData.customDurationDays = '';
        this.$nextTick(() => {
            const input = document.getElementById('customDurationDays');
            if (input) input.focus();
        });
    },

    closeCustomDurationModal() {
        this.customDurationModalVisible = false;
        this.passengerData.customDurationDays = '';
    },

    saveCustomDuration() {
        const days = parseInt(this.passengerData.customDurationDays);
        const { minDays, maxDays } = this.stayDurationLimits;

        if (isNaN(days) || days < minDays || days > maxDays) {
            alert(`Please enter a valid duration between ${minDays} and ${maxDays} days`);
            return;
        }

        this.passengerData.stay_duration = `Customized (${days} Days)`;
        this.passengerData.stay_duration_int = days;
        this.passengerData.stay_duration_display = `Customized (${days} Days)`;

        const select = document.querySelector('select[x-model="passengerData.stay_duration"]');
        if (select) {
            let customOption = Array.from(select.options).find(opt => opt.value.startsWith('Customized'));
            if (!customOption) {
                customOption = document.createElement('option');
                select.appendChild(customOption);
            }
            customOption.value = `Customized (${days} Days)`;
            customOption.textContent = `Customized (${days} Days)`;
            select.value = `Customized (${days} Days)`;
        }

        this.closeCustomDurationModal();
        this.calculatePassengerType();
    },

    parseStayDurationDays(stayDuration) {
        if (!stayDuration) return null;
        if (/^\d+$/.test(stayDuration)) {
            return parseInt(stayDuration, 10);
        }
        const match = stayDuration.match(/(\d+)\s*days?/i);
        return match ? parseInt(match[1], 10) : null;
    },

    getStayDurationValue() {
        return this.parseStayDurationDays(this.passengerData.stay_duration);
    },

    parseFlightDateRange(rangeString) {
        if (!rangeString) return null;
        const parts = rangeString.split(' - ');
        if (parts.length !== 2) return null;
        const months = {
            'Jan': 0, 'Feb': 1, 'Mar': 2, 'Apr': 3, 'May': 4, 'Jun': 5,
            'Jul': 6, 'Aug': 7, 'Sep': 8, 'Oct': 9, 'Nov': 10, 'Dec': 11
        };
        const parseDate = (dateStr) => {
            const match = dateStr.trim().match(/^(\w+)\s+(\d+),\s+(\d{4})$/);
            if (!match) return null;
            const month = months[match[1]];
            const day = parseInt(match[2]);
            const year = parseInt(match[3]);
            if (month === undefined) return null;
            return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        };
        const fromDate = parseDate(parts[0]);
        const toDate = parseDate(parts[1]);
        if (!fromDate || !toDate) return null;
        return { from: fromDate, to: toDate };
    },

    /**
     * Shared required-field guard for Flight Date Range + Stay Duration.
     * Every passenger create/edit form must call this before pushing to the
     * list or POSTing. Returns { from, to, stayDuration } or null (alerts).
     * Server-side FlightDateSlot rule remains authoritative.
     */
    validatePassengerSchedule() {
        const range = this.passengerData.flight_date_range
            ? this.parseFlightDateRange(this.passengerData.flight_date_range)
            : null;
        if (!range || !range.from || !range.to) {
            alert('Please select a valid Flight Date Range');
            return null;
        }
        const stayDuration = this.parseStayDurationDays(this.passengerData.stay_duration);
        const { minDays, maxDays } = this.stayDurationLimits;
        if (!stayDuration || stayDuration < minDays || stayDuration > maxDays) {
            alert(`Please select a valid Stay Duration (${minDays}-${maxDays} days)`);
            return null;
        }
        return { from: range.from, to: range.to, stayDuration };
    },
};
