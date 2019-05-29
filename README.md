# IOTA Pay Per Content Wordpress Plugin
We are realeasing this Wordpress Plugin developed by the [IOTA Argentina Community Cluster](http://iotaargentina.org) to allow IOTA payments on certain posts using IOTA. 
You can chose whether to wait for transactions to be confirmed or not in order to grant access to contents.

**IMPORTANT:** this is a work in progress and we encourage the community to help us improving the concept. Feel free to submit issues or pull requests if you have tested the code proposed. 

# Installation

1. Clone this repository or download it as zip. If you do the later, rename the folder to **iota-ppc-wp-plugin**. 
2. Upload the folder to your wp-content/plugins Wordpress directory
3. Head to Plugins section on your Dashboard and activate the Plugin
4. Go to IOTA PPC Configuration page and enter a Node (you can use one from [iota.dance](https://iota.dance))
5. Enter the address in which you will receive the payments
6. Select if you want buyers to wait until the transaction is confirmed or not. 

# Using the PPC on Posts
Once the plugin is installed you will find a box at your Entries page. In order to require a payment for a given post you need to check the "Request IOTA payment to access" box, enter an amount and select the units (i.e. Iota/Kiota/Miota)
You will also need to provide some text into the Excerpt box, to display as an advance of the blocked content.

![Check the Pay with IOTA Box and enter price and units](http://iotaargentina.org/public/ppc-plugin-page.png)

# Making the payments

Posts that require an IOTA payment will display the Title, Excerpt content and a QR Code, Deep Link and the Transaction data. To use Deep Links users must enable them on their Trinity Wallet. 
Once the payment is sent, users can click on "Verify Payment" and they should be redirected to the complete content. 

![Check the Pay with IOTA Box and enter price and units](http://iotaargentina.org/public/ppc-payment.png)

# Considerations

1. Issuing a fake transaction is easy on IOTA so, if you want to be 100% sure about the payment of a given content it might be a good idea to wait for the transaction to be confirmed (this can be done at your IOTA PPC configuration page)
2. At the moment of this release Deep Links are not supported in all Trinity versions (they should work on Android and Iphone last releases)
3. Feel free to style the components at index.php to match your design. Again, this is just a PoC and we hope the community help us to improve it. 






