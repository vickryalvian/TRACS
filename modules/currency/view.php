<div class="card tracs-card currency-card">

    <div class="card-header tracs-card-header">
        <div class="card-title">Currency Converter</div>
    </div>

    <div class="card-body tracs-card-body currency-body">

        <div class="currency-row">

            <select id="currency-from" class="tracs-input">
                <option value="IDR">IDR</option>
                <option value="USD">USD</option>
                <option value="SGD">SGD</option>
            </select>

            <button id="swap-currency" class="tracs-icon-btn">⇄</button>

            <select id="currency-to" class="tracs-input">
                <option value="USD">USD</option>
                <option value="IDR">IDR</option>
                <option value="SGD">SGD</option>
            </select>

        </div>

        <input
            type="number"
            id="currency-amount"
            class="tracs-input"
            placeholder="Transfer amount"
            value="1000000"
        >

        <button id="convert-btn" class="tracs-btn-primary">
            Convert
        </button>

        <div class="currency-result">

            <div id="currency-result" class="currency-value">-</div>

            <div id="currency-rate" class="currency-sub"></div>

        </div>

        <div class="currency-updated">
            <span class="currency-meta-label">Updated:</span>
            <span id="currency-time"></span>
        </div>

    </div>

</div>
